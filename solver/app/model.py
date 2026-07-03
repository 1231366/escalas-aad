"""Motor de regras do solver (H1-H5, H7-H10, S1-S8; H6 reclassificado para
S7 — ver ADR-0006). Lógica pura, sem FastAPI.

Ver CONTEXT.md (IDs canónicos das regras) e docs/adr/0002..0006.

Este módulo tem duas entradas:
  - ``solve_schedule``: constrói e resolve o modelo CP-SAT para /generate.
  - ``check_hard_rules``: verifica diretamente as regras hard sobre um
    conjunto de assignments completo, para /validate (não usa o solver).

As duas partilham os mesmos helpers de datas/agrupamento para que uma
escala gerada por ``solve_schedule`` seja sempre considerada válida por
``check_hard_rules``.
"""

from __future__ import annotations

import datetime as dt
from collections import defaultdict

from ortools.sat.python import cp_model

from app.schemas import (
    Absence,
    Assignment,
    CoverageRule,
    Employee,
    GenerateRequest,
    GenerateResponse,
    Regime,
    RuleConfig,
    Shift,
    SolveStatus,
    SolverParams,
    SwapCandidate,
    SwapCandidatesRequest,
    ValidateRequest,
    VacationImpactRequest,
    Violation,
)

STATES: list[str] = ["M", "T", "N", "F"]
WORK_STATES: list[str] = ["M", "T", "N"]

# Precisão usada para converter horas (que podem ser 37.5) em inteiros para o
# CP-SAT. 4 => 15 minutos, suficiente para 37.5/40/4.0/8.0 sem qualquer perda.
_SCALE = 4


# ---------------------------------------------------------------------------
# Helpers de datas / agrupamento (partilhados entre o solver e o validador)
# ---------------------------------------------------------------------------


def _period_dates(start: dt.date, end: dt.date) -> list[dt.date]:
    return [start + dt.timedelta(days=i) for i in range((end - start).days + 1)]


def _weekday(d: dt.date) -> int:
    """0=segunda ... 6=domingo (ISO) — igual a date.weekday()."""
    return d.weekday()


def _iso_week_key(d: dt.date) -> tuple[int, int]:
    iso = d.isocalendar()
    return (iso[0], iso[1])


def _month_key(d: dt.date) -> tuple[int, int]:
    return (d.year, d.month)


def _weekly_groups(dates: list[dt.date]) -> dict[tuple[int, int], list[dt.date]]:
    groups: dict[tuple[int, int], list[dt.date]] = defaultdict(list)
    for d in dates:
        groups[_iso_week_key(d)].append(d)
    return dict(groups)


def _monthly_groups(dates: list[dt.date]) -> dict[tuple[int, int], list[dt.date]]:
    groups: dict[tuple[int, int], list[dt.date]] = defaultdict(list)
    for d in dates:
        groups[_month_key(d)].append(d)
    return dict(groups)


def _coverage_map(coverage: list[CoverageRule]) -> dict[tuple[int, str], int]:
    return {(rule.weekday, rule.shift.value): rule.required for rule in coverage}


def _forbidden_set(config: RuleConfig) -> set[tuple[str, str]]:
    return {(a.value, b.value) for a, b in config.forbidden_transitions}


def _shift_allowed_by_regime(employee: Employee, state: str) -> bool:
    """H8: fixa_noite só N/F; regime DIA nunca N; regime NOITE nunca M/T."""
    if employee.fixa_noite and state not in ("N", "F"):
        return False
    if employee.regime == Regime.DIA and state == "N":
        return False
    if employee.regime == Regime.NOITE and state in ("M", "T"):
        return False
    return True


def _is_absent(employee_id: int, d: dt.date, absences) -> bool:
    return any(a.employee_id == employee_id and a.start <= d <= a.end for a in absences)


def _week_shift_count_feasible(
    contract_hours: float, tolerance: float, shift_hours: float, len_week: int
) -> bool:
    """H7 é proporcional aos dias incluídos numa semana parcial (ADR/H7), mas com
    turnos atómicos de N horas isso pode gerar uma janela [lower, upper] que não
    contém nenhum múltiplo de shift_hours (tipicamente semanas de 1-2 dias na
    fronteira do período). Nesse caso não há dados suficientes para avaliar H7
    com esta granularidade — a semana é ignorada em vez de forçar uma
    inviabilidade artificial. Usada por solve_schedule e check_hard_rules para
    que ambos concordem sempre sobre que semanas são avaliáveis."""

    lower = (contract_hours - 8) * len_week / 7
    upper = (contract_hours + tolerance) * len_week / 7
    for k in range(0, len_week + 1):
        hours = shift_hours * k
        if lower - 1e-6 <= hours <= upper + 1e-6:
            return True
    return False


def _week_effective_max_shifts(
    contract_hours: float, tolerance: float, shift_hours: float, len_week: int
) -> int:
    """Máximo de turnos/semana usável no pré-check aritmético: o limite do H7
    quando a janela é avaliável, ou o limite puramente físico (um turno por
    dia) quando a semana é demasiado curta para o H7 se aplicar (ver
    ``_week_shift_count_feasible``)."""

    if not _week_shift_count_feasible(contract_hours, tolerance, shift_hours, len_week):
        return len_week
    upper = (contract_hours + tolerance) * len_week / 7
    return int(upper // shift_hours)


# ---------------------------------------------------------------------------
# Verificações aritméticas rápidas de inviabilidade (antes de chamar o CP-SAT)
# ---------------------------------------------------------------------------


def _arithmetic_infeasibility_checks(request: GenerateRequest) -> list[Violation]:
    violations: list[Violation] = []
    dates = _period_dates(request.period_start, request.period_end)
    coverage = _coverage_map(request.coverage)
    employees = request.employees
    tolerance = request.config.hour_bank_weekly_tolerance
    shift_hours = request.config.shift_hours

    # H1/H8 — capacidade diária por turno vs. pessoas elegíveis nesse dia
    for d in dates:
        weekday = _weekday(d)
        for shift in WORK_STATES:
            required = coverage.get((weekday, shift), 0)
            if required <= 0:
                continue
            eligible = [
                e
                for e in employees
                if _shift_allowed_by_regime(e, shift)
                and not _is_absent(e.id, d, request.absences)
            ]
            if required > len(eligible):
                violations.append(
                    Violation(
                        rule="H1",
                        date=d,
                        message=(
                            f"H1: cobertura impossível em {d.isoformat()}: turno {shift} "
                            f"exige {required} mas há apenas {len(eligible)} pessoa(s) "
                            "elegível(eis) (regime/fixa_noite/ausências)."
                        ),
                    )
                )

    # H7 — oferta máxima de turnos-pessoa por semana vs. procura da cobertura
    for week_dates in _weekly_groups(dates).values():
        len_week = len(week_dates)
        demand = sum(coverage.get((_weekday(d), s), 0) for d in week_dates for s in WORK_STATES)
        supply = 0
        for e in employees:
            supply += _week_effective_max_shifts(e.contract_hours, tolerance, shift_hours, len_week)
        if demand > supply:
            monday = min(week_dates)
            violations.append(
                Violation(
                    rule="H7",
                    date=monday,
                    message=(
                        f"H7: faltam {demand - supply} turnos-pessoa na semana de "
                        f"{monday.isoformat()} (oferta máxima {supply}, procura {demand}). "
                        "Sugestão: aumentar hour_bank_weekly_tolerance ou reduzir cobertura "
                        "ao fim de semana (ADR-0003)."
                    ),
                )
            )
    return violations


# ---------------------------------------------------------------------------
# CP-SAT: construção e resolução do modelo (/generate)
# ---------------------------------------------------------------------------


class _Indicators:
    """Indicador de estado (variável CP-SAT ou constante 0/1) que atravessa a
    fronteira entre ``initial_state`` (mês anterior) e o período a gerar."""

    def __init__(
        self,
        x: dict[tuple[int, dt.date, str], cp_model.IntVar],
        period_start: dt.date,
        period_end: dt.date,
        initial_state: list[Assignment],
    ) -> None:
        self._x = x
        self._start = period_start
        self._end = period_end
        self._known: dict[int, dict[dt.date, Shift | None]] = {}
        for a in initial_state:
            self._known.setdefault(a.employee_id, {})[a.date] = a.shift

    def state(self, employee_id: int, d: dt.date, state: str):
        if self._start <= d <= self._end:
            return self._x[employee_id, d, state]
        known = self._known.get(employee_id, {})
        if d in known:
            actual = "F" if known[d] is None else known[d].value
            return 1 if actual == state else 0
        return None

    def is_f(self, employee_id: int, d: dt.date):
        return self.state(employee_id, d, "F")

    def is_working(self, employee_id: int, d: dt.date):
        f = self.is_f(employee_id, d)
        if f is None:
            return None
        return 1 - f

    def earliest(self, employee_id: int) -> dt.date:
        known = self._known.get(employee_id, {})
        if not known:
            return self._start
        return min([self._start, *known.keys()])


def _and2(model: cp_model.CpModel, a, b, name: str):
    """AND de dois indicadores (int 0/1 ou BoolVar/expr)."""
    if isinstance(a, int) and isinstance(b, int):
        return 1 if (a and b) else 0
    if isinstance(a, int):
        return b if a else 0
    if isinstance(b, int):
        return a if b else 0
    z = model.NewBoolVar(name)
    model.Add(z <= a)
    model.Add(z <= b)
    model.Add(z >= a + b - 1)
    return z


def _and3(model: cp_model.CpModel, a, b, c, name: str):
    ab = _and2(model, a, b, name + "_ab")
    return _and2(model, ab, c, name + "_abc")


def solve_schedule(request: GenerateRequest) -> GenerateResponse:
    pre_check = _arithmetic_infeasibility_checks(request)
    if pre_check:
        return GenerateResponse(status=SolveStatus.INFEASIBLE, conflicts=pre_check)

    dates = _period_dates(request.period_start, request.period_end)
    employees = request.employees
    config = request.config
    coverage = _coverage_map(request.coverage)
    forbidden = _forbidden_set(config)

    model = cp_model.CpModel()
    x: dict[tuple[int, dt.date, str], cp_model.IntVar] = {}
    for e in employees:
        for d in dates:
            for s in STATES:
                x[e.id, d, s] = model.NewBoolVar(f"x_e{e.id}_{d.isoformat()}_{s}")

    ind = _Indicators(x, request.period_start, request.period_end, request.initial_state)

    # H2 — exatamente um estado por pessoa/dia
    for e in employees:
        for d in dates:
            model.AddExactlyOne(x[e.id, d, s] for s in STATES)

    # H1 — cobertura exata por turno/dia
    for d in dates:
        weekday = _weekday(d)
        for s in WORK_STATES:
            required = coverage.get((weekday, s))
            if required is None:
                continue
            model.Add(sum(x[e.id, d, s] for e in employees) == required)

    # H8 — regime / fixa_noite
    for e in employees:
        for d in dates:
            for s in STATES:
                if not _shift_allowed_by_regime(e, s):
                    model.Add(x[e.id, d, s] == 0)

    # H10 — ausências obrigam folga
    for a in request.absences:
        for d in dates:
            if a.start <= d <= a.end:
                model.Add(x[a.employee_id, d, "F"] == 1)

    # H3 — matriz de descanso (transições proibidas), incluindo fronteira com initial_state
    boundary_days = [request.period_start - dt.timedelta(days=1), *dates[:-1]]
    for e in employees:
        for d in boundary_days:
            d2 = d + dt.timedelta(days=1)
            for s1, s2 in forbidden:
                i1 = ind.state(e.id, d, s1)
                i2 = ind.state(e.id, d2, s2)
                if i1 is None or i2 is None:
                    continue
                if isinstance(i1, int) and isinstance(i2, int):
                    continue
                model.Add(i1 + i2 <= 1)

    # H4 — quem faz M num mês não faz N nesse mês (e vice-versa)
    for e in employees:
        for month_dates in _monthly_groups(dates).values():
            use_m = model.NewBoolVar(f"useM_e{e.id}_{month_dates[0].isoformat()}")
            use_n = model.NewBoolVar(f"useN_e{e.id}_{month_dates[0].isoformat()}")
            model.AddMaxEquality(use_m, [x[e.id, d, "M"] for d in month_dates])
            model.AddMaxEquality(use_n, [x[e.id, d, "N"] for d in month_dates])
            model.Add(use_m + use_n <= 1)

    # H5 — ≥1 par FF em cada janela deslizante de ff_window_weeks semanas
    window_len_ff = config.ff_window_weeks * 7
    for e in employees:
        ws = ind.earliest(e.id)
        while ws + dt.timedelta(days=window_len_ff - 1) <= request.period_end:
            pair_terms = []
            skip_window = False
            for k in range(window_len_ff - 1):
                d1 = ws + dt.timedelta(days=k)
                d2 = d1 + dt.timedelta(days=1)
                f1 = ind.is_f(e.id, d1)
                f2 = ind.is_f(e.id, d2)
                if f1 is None or f2 is None:
                    continue
                pair = _and2(model, f1, f2, f"ff_e{e.id}_{d1.isoformat()}")
                if isinstance(pair, int):
                    if pair == 1:
                        skip_window = True
                        break
                    continue
                pair_terms.append(pair)
            if not skip_window and pair_terms:
                model.AddBoolOr(pair_terms)
            ws += dt.timedelta(days=1)

    # Pares de folgas consecutivas (FF) dentro do período — indicador partilhado
    # entre S7 (peso 5, por pessoa×mês) e S8 (peso 8, equidade entre pessoas).
    # H6 (FF 1x/mês obrigatório) foi reclassificado para S7 soft — ADR-0006:
    # exigi-lo como hard tornava uma única semana isolada infeasible (só 21
    # folgas/semana para 12 pessoas, média 1,75 — nem todas cabem num par FF).
    ff_pair_vars: dict[int, dict[dt.date, object]] = defaultdict(dict)
    for e in employees:
        for i in range(len(dates) - 1):
            d1, d2 = dates[i], dates[i + 1]
            f1 = ind.is_f(e.id, d1)
            f2 = ind.is_f(e.id, d2)
            if f1 is None or f2 is None:
                continue
            ff_pair_vars[e.id][d1] = _and2(model, f1, f2, f"ff_pair_e{e.id}_{d1.isoformat()}")

    # H9 — nunca mais de max_consecutive_work_days dias de trabalho seguidos
    window_len_wd = config.max_consecutive_work_days + 1
    for e in employees:
        ws = ind.earliest(e.id)
        while ws + dt.timedelta(days=window_len_wd - 1) <= request.period_end:
            terms = []
            for k in range(window_len_wd):
                d = ws + dt.timedelta(days=k)
                w = ind.is_working(e.id, d)
                if w is None:
                    terms = None
                    break
                terms.append(w)
            if terms is not None:
                total = sum(terms)
                if not isinstance(total, int):
                    model.Add(total <= config.max_consecutive_work_days)
            ws += dt.timedelta(days=1)

    # H7 — carga semanal dentro do contrato ± tolerância (semanas ISO, proporcional)
    shift_hours_i = round(config.shift_hours * _SCALE)
    tolerance_i = round(config.hour_bank_weekly_tolerance * _SCALE)
    for e in employees:
        contract_i = round(e.contract_hours * _SCALE)
        for week_dates in _weekly_groups(dates).values():
            len_week = len(week_dates)
            if not _week_shift_count_feasible(
                e.contract_hours, config.hour_bank_weekly_tolerance, config.shift_hours, len_week
            ):
                continue
            hours_expr = sum(
                shift_hours_i * x[e.id, d, s] for d in week_dates for s in WORK_STATES
            )
            model.Add(7 * hours_expr <= (contract_i + tolerance_i) * len_week)
            model.Add(7 * hours_expr >= (contract_i - 8 * _SCALE) * len_week)

    # --- Objetivo: soft rules S1, S2, S3, S5, S6, S7, S8 (S4 fora de âmbito por agora) ---
    objective_terms: list[tuple[int, object]] = []

    day_pattern = ["M", "M", "M", "F", "T", "T", "T"]
    night_pattern = ["N", "N", "N", "F", "F"]

    day_eligible = [e for e in employees if e.regime != Regime.NOITE and not e.fixa_noite]
    night_eligible = [e for e in employees if e.regime != Regime.DIA]

    # S1 — padrão MMMFTTT (peso 3)
    for e in day_eligible:
        for d in dates:
            idx = (d - request.period_start).days % 7
            expected = day_pattern[idx]
            objective_terms.append((3, 1 - x[e.id, d, expected]))

    # S2 — padrão NNNFF (peso 3)
    for e in night_eligible:
        for d in dates:
            idx = (d - request.period_start).days % 5
            expected = night_pattern[idx]
            objective_terms.append((3, 1 - x[e.id, d, expected]))

    # S3 — equidade de fins de semana trabalhados (peso 8)
    weekend_totals = [
        sum(1 - x[e.id, d, "F"] for d in dates if _weekday(d) in (5, 6)) for e in employees
    ]
    if weekend_totals:
        max_w = model.NewIntVar(0, len(dates), "max_weekend")
        min_w = model.NewIntVar(0, len(dates), "min_weekend")
        for expr in weekend_totals:
            model.Add(max_w >= expr)
            model.Add(min_w <= expr)
        objective_terms.append((8, max_w - min_w))

    # S6 — equidade de carga total (peso 8)
    max_possible_hours_i = shift_hours_i * len(dates)
    total_hours = [
        sum(shift_hours_i * x[e.id, d, s] for d in dates for s in WORK_STATES)
        for e in employees
    ]
    if total_hours:
        max_h = model.NewIntVar(0, max_possible_hours_i, "max_hours")
        min_h = model.NewIntVar(0, max_possible_hours_i, "min_hours")
        for expr in total_hours:
            model.Add(max_h >= expr)
            model.Add(min_h <= expr)
        objective_terms.append((8, max_h - min_h))

    # S5 — turnos isolados: trabalho com folga antes e depois (peso 1)
    for e in employees:
        for d in dates:
            before = ind.is_f(e.id, d - dt.timedelta(days=1))
            after = ind.is_f(e.id, d + dt.timedelta(days=1))
            if before is None or after is None:
                continue
            working = 1 - x[e.id, d, "F"]
            isolated = _and3(model, before, working, after, f"iso_e{e.id}_{d.isoformat()}")
            objective_terms.append((1, isolated))

    # S7 — FF 1x/mês (preferível, ex-H6 reclassificado — peso 5, ADR-0006)
    if config.ff_monthly:
        for e in employees:
            pairs_by_date = ff_pair_vars[e.id]
            for month_key, month_dates in _monthly_groups(dates).items():
                pair_terms = [
                    pairs_by_date[month_dates[i]]
                    for i in range(len(month_dates) - 1)
                    if month_dates[i] in pairs_by_date
                ]
                if not pair_terms:
                    continue
                has_ff = model.NewBoolVar(
                    f"s7_has_ff_e{e.id}_{month_key[0]}{month_key[1]:02d}"
                )
                model.AddMaxEquality(has_ff, pair_terms)
                objective_terms.append((5, 1 - has_ff))

    # S8 — equidade do nº de pares FF entre pessoas no período (peso 8, ADR-0006)
    ff_pair_counts = [
        sum(ff_pair_vars[e.id].values()) if ff_pair_vars[e.id] else 0 for e in employees
    ]
    if ff_pair_counts:
        max_ff_count = model.NewIntVar(0, len(dates), "max_ff_pairs")
        min_ff_count = model.NewIntVar(0, len(dates), "min_ff_pairs")
        for expr in ff_pair_counts:
            model.Add(max_ff_count >= expr)
            model.Add(min_ff_count <= expr)
        objective_terms.append((8, max_ff_count - min_ff_count))

    model.Minimize(sum(weight * term for weight, term in objective_terms))

    params: SolverParams = request.solver_params
    solver = cp_model.CpSolver()
    solver.parameters.max_time_in_seconds = params.max_time_in_seconds
    solver.parameters.num_search_workers = params.num_search_workers

    status = solver.Solve(model)

    if status in (cp_model.OPTIMAL, cp_model.FEASIBLE):
        assignments = []
        for e in employees:
            for d in dates:
                chosen = next(s for s in STATES if solver.Value(x[e.id, d, s]) == 1)
                assignments.append(
                    Assignment(
                        employee_id=e.id,
                        date=d,
                        shift=None if chosen == "F" else Shift(chosen),
                    )
                )
        return GenerateResponse(
            status=SolveStatus.FEASIBLE,
            assignments=assignments,
            objective=solver.ObjectiveValue(),
            wall_time_s=solver.WallTime(),
        )

    if status == cp_model.INFEASIBLE:
        return GenerateResponse(
            status=SolveStatus.INFEASIBLE,
            conflicts=[
                Violation(
                    rule="INFEASIBLE",
                    message=(
                        "O modelo não tem solução viável apesar de as verificações "
                        "aritméticas terem passado. Sugestões: aumentar "
                        "hour_bank_weekly_tolerance, reduzir cobertura ao fim de semana, "
                        "ou rever regimes/fixa_noite (o pool de noite precisa de ≥4 "
                        "pessoas elegíveis — ADR-0004 Q2)."
                    ),
                )
            ],
            wall_time_s=solver.WallTime(),
        )

    # UNKNOWN / MODEL_INVALID: o solver esgotou o tempo sem encontrar solução
    return GenerateResponse(status=SolveStatus.TIMEOUT, wall_time_s=solver.WallTime())


# ---------------------------------------------------------------------------
# Validação direta das regras hard sobre uma escala completa (/validate)
# ---------------------------------------------------------------------------


def check_hard_rules(request: ValidateRequest) -> list[Violation]:
    violations: list[Violation] = []
    dates = _period_dates(request.period_start, request.period_end)
    employees = {e.id: e for e in request.employees}
    coverage = _coverage_map(request.coverage)
    forbidden = _forbidden_set(request.config)

    assignments_map: dict[tuple[int, dt.date], Shift | None] = {
        (a.employee_id, a.date): a.shift for a in request.assignments
    }
    initial_map: dict[tuple[int, dt.date], Shift | None] = {
        (a.employee_id, a.date): a.shift for a in request.initial_state
    }

    def state_at(employee_id: int, d: dt.date) -> str | None:
        if request.period_start <= d <= request.period_end:
            key = (employee_id, d)
            if key not in assignments_map:
                return None
            shift = assignments_map[key]
            return "F" if shift is None else shift.value
        key = (employee_id, d)
        if key in initial_map:
            shift = initial_map[key]
            return "F" if shift is None else shift.value
        return None

    # H1 — cobertura exata
    for d in dates:
        weekday = _weekday(d)
        for s in WORK_STATES:
            required = coverage.get((weekday, s))
            if required is None:
                continue
            count = sum(1 for e_id in employees if state_at(e_id, d) == s)
            if count != required:
                violations.append(
                    Violation(
                        rule="H1",
                        date=d,
                        message=(
                            f"H1: cobertura errada em {d.isoformat()} turno {s}: "
                            f"esperado {required}, encontrado {count}."
                        ),
                    )
                )

    # H2 — exatamente um estado por pessoa/dia (deteta atribuições em falta)
    for e_id in employees:
        for d in dates:
            if (e_id, d) not in assignments_map:
                violations.append(
                    Violation(
                        rule="H2",
                        date=d,
                        employee_id=e_id,
                        message=f"H2: falta atribuição para a pessoa {e_id} em {d.isoformat()}.",
                    )
                )

    # H3 — transições proibidas (matriz de descanso), incluindo fronteira com initial_state
    boundary_days = [request.period_start - dt.timedelta(days=1), *dates[:-1]]
    for e_id in employees:
        for d in boundary_days:
            d2 = d + dt.timedelta(days=1)
            s1 = state_at(e_id, d)
            s2 = state_at(e_id, d2)
            if s1 is None or s2 is None:
                continue
            if (s1, s2) in forbidden:
                violations.append(
                    Violation(
                        rule="H3",
                        date=d,
                        employee_id=e_id,
                        message=(
                            f"H3: transição proibida {s1}->{s2} entre {d.isoformat()} e "
                            f"{d2.isoformat()} para a pessoa {e_id}."
                        ),
                    )
                )

    # H4 — M e N incompatíveis no mesmo mês
    for e_id in employees:
        for month_dates in _monthly_groups(dates).values():
            states = {state_at(e_id, d) for d in month_dates}
            if "M" in states and "N" in states:
                violations.append(
                    Violation(
                        rule="H4",
                        employee_id=e_id,
                        date=month_dates[0],
                        message=(
                            f"H4: pessoa {e_id} tem turnos M e N no mesmo mês "
                            f"({month_dates[0].strftime('%Y-%m')})."
                        ),
                    )
                )

    # H5 — ≥1 par FF em cada janela deslizante de ff_window_weeks semanas
    window_len_ff = request.config.ff_window_weeks * 7
    for e_id in employees:
        known_dates = sorted(
            {d for (emp, d) in initial_map if emp == e_id} | set(dates)
        )
        earliest = known_dates[0] if known_dates else request.period_start
        ws = earliest
        while ws + dt.timedelta(days=window_len_ff - 1) <= request.period_end:
            window_days = [ws + dt.timedelta(days=k) for k in range(window_len_ff)]
            states = [state_at(e_id, d) for d in window_days]
            if any(s is None for s in states):
                ws += dt.timedelta(days=1)
                continue
            has_ff = any(states[i] == "F" and states[i + 1] == "F" for i in range(len(states) - 1))
            if not has_ff:
                violations.append(
                    Violation(
                        rule="H5",
                        employee_id=e_id,
                        date=ws,
                        message=(
                            "H5: sem par de folgas consecutivas na janela de "
                            f"{ws.isoformat()} a {window_days[-1].isoformat()} para a "
                            f"pessoa {e_id}."
                        ),
                    )
                )
            ws += dt.timedelta(days=1)

    # H6 removido: reclassificado para S7 (soft) — ADR-0006. Não reutilizar o ID.

    # H7 — carga semanal dentro do contrato ± tolerância (proporcional em semanas parciais)
    for e_id, employee in employees.items():
        for week_dates in _weekly_groups(dates).values():
            len_week = len(week_dates)
            if not _week_shift_count_feasible(
                employee.contract_hours,
                request.config.hour_bank_weekly_tolerance,
                request.config.shift_hours,
                len_week,
            ):
                continue
            total_hours = sum(
                request.config.shift_hours for d in week_dates if state_at(e_id, d) in WORK_STATES
            )
            upper = (
                (employee.contract_hours + request.config.hour_bank_weekly_tolerance)
                * len_week
                / 7
            )
            lower = (employee.contract_hours - 8) * len_week / 7
            monday = min(week_dates)
            if total_hours > upper + 1e-6:
                violations.append(
                    Violation(
                        rule="H7",
                        employee_id=e_id,
                        date=monday,
                        message=(
                            f"H7: pessoa {e_id} excede a carga semanal na semana de "
                            f"{monday.isoformat()}: {total_hours}h > {upper:.2f}h."
                        ),
                    )
                )
            elif total_hours < lower - 1e-6:
                violations.append(
                    Violation(
                        rule="H7",
                        employee_id=e_id,
                        date=monday,
                        message=(
                            f"H7: pessoa {e_id} fica abaixo da carga semanal mínima na "
                            f"semana de {monday.isoformat()}: {total_hours}h < {lower:.2f}h."
                        ),
                    )
                )

    # H8 — regime / fixa_noite
    for e_id, employee in employees.items():
        for d in dates:
            s = state_at(e_id, d)
            if s is None:
                continue
            if not _shift_allowed_by_regime(employee, s):
                violations.append(
                    Violation(
                        rule="H8",
                        employee_id=e_id,
                        date=d,
                        message=(
                            f"H8: pessoa {e_id} tem turno {s} em {d.isoformat()} "
                            "incompatível com o regime/fixa_noite."
                        ),
                    )
                )

    # H9 — nunca mais de max_consecutive_work_days dias de trabalho seguidos
    window_len_wd = request.config.max_consecutive_work_days + 1
    for e_id in employees:
        known_dates = sorted(
            {d for (emp, d) in initial_map if emp == e_id} | set(dates)
        )
        earliest = known_dates[0] if known_dates else request.period_start
        ws = earliest
        while ws + dt.timedelta(days=window_len_wd - 1) <= request.period_end:
            window_days = [ws + dt.timedelta(days=k) for k in range(window_len_wd)]
            states = [state_at(e_id, d) for d in window_days]
            if any(s is None for s in states):
                ws += dt.timedelta(days=1)
                continue
            worked = sum(1 for s in states if s in WORK_STATES)
            if worked > request.config.max_consecutive_work_days:
                violations.append(
                    Violation(
                        rule="H9",
                        employee_id=e_id,
                        date=ws,
                        message=(
                            f"H9: pessoa {e_id} tem mais de "
                            f"{request.config.max_consecutive_work_days} dias de trabalho "
                            f"seguidos a partir de {ws.isoformat()}."
                        ),
                    )
                )
            ws += dt.timedelta(days=1)

    # H10 — ausências
    for absence in request.absences:
        for d in dates:
            if absence.start <= d <= absence.end:
                s = state_at(absence.employee_id, d)
                if s is not None and s != "F":
                    violations.append(
                        Violation(
                            rule="H10",
                            employee_id=absence.employee_id,
                            date=d,
                            message=(
                                f"H10: pessoa {absence.employee_id} deveria estar de folga "
                                f"(ausência) em {d.isoformat()} mas tem turno {s}."
                            ),
                        )
                    )

    return violations


# ---------------------------------------------------------------------------
# Candidatas a troca e teste de impacto de férias (/swap-candidates,
# /vacation-impact) — ambos reutilizam check_hard_rules sobre a escala
# completa alterada, nunca reimplementam regras (ADR-0002).
# ---------------------------------------------------------------------------


def _as_validate_request(
    request: ValidateRequest, assignments: list[Assignment]
) -> ValidateRequest:
    """Reconstrói um ValidateRequest "plano" a partir de qualquer subclasse
    (SwapCandidatesRequest/VacationImpactRequest), descartando os campos
    extra específicos do endpoint e substituindo os assignments."""

    return ValidateRequest(
        period_start=request.period_start,
        period_end=request.period_end,
        employees=request.employees,
        coverage=request.coverage,
        config=request.config,
        absences=request.absences,
        initial_state=request.initial_state,
        solver_params=request.solver_params,
        assignments=assignments,
    )


def swap_candidates(request: SwapCandidatesRequest) -> list[SwapCandidate]:
    """Para o assignment da ``requester`` em ``date``, devolve as colegas com
    quem trocar esse dia mantém todas as regras hard na escala completa
    resultante — nunca só os 2 dias trocados (ADR-0002)."""

    assignments_by_key: dict[tuple[int, dt.date], Assignment] = {
        (a.employee_id, a.date): a for a in request.assignments
    }
    requester_key = (request.requester_employee_id, request.date)
    requester_assignment = assignments_by_key.get(requester_key)
    if requester_assignment is None:
        return []

    candidates: list[SwapCandidate] = []
    for e in request.employees:
        if e.id == request.requester_employee_id:
            continue
        colleague_key = (e.id, request.date)
        colleague_assignment = assignments_by_key.get(colleague_key)
        if colleague_assignment is None:
            continue
        if colleague_assignment.shift == requester_assignment.shift:
            continue  # trocar igual por igual não é troca

        swapped = dict(assignments_by_key)
        swapped[requester_key] = Assignment(
            employee_id=request.requester_employee_id,
            date=request.date,
            shift=colleague_assignment.shift,
        )
        swapped[colleague_key] = Assignment(
            employee_id=e.id,
            date=request.date,
            shift=requester_assignment.shift,
        )

        violations = check_hard_rules(_as_validate_request(request, list(swapped.values())))
        if not violations:
            candidates.append(SwapCandidate(employee_id=e.id, shift=colleague_assignment.shift))

    return candidates


def vacation_impact(request: VacationImpactRequest) -> list[Violation]:
    """Simula ``employee_id`` de folga (H10) entre ``start`` e ``end`` sobre
    os assignments dados e devolve as violações hard resultantes — tipicamente
    buracos de cobertura (H1) que a ausência cria (ADR-0002)."""

    assignments = [
        Assignment(employee_id=a.employee_id, date=a.date, shift=None)
        if a.employee_id == request.employee_id and request.start <= a.date <= request.end
        else a
        for a in request.assignments
    ]
    absences = [
        *request.absences,
        Absence(employee_id=request.employee_id, start=request.start, end=request.end),
    ]

    validate_request = ValidateRequest(
        period_start=request.period_start,
        period_end=request.period_end,
        employees=request.employees,
        coverage=request.coverage,
        config=request.config,
        absences=absences,
        initial_state=request.initial_state,
        solver_params=request.solver_params,
        assignments=assignments,
    )
    return check_hard_rules(validate_request)
