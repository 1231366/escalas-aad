"""test_generate_month: mês completo (agosto 2026) FEASIBLE em <60s.
Verifica H4 (M x N incompatíveis no mês), S7 (FF/mês, preferência soft —
ex-H6, ADR-0006), S8 (equidade do nº de pares FF) e H9 (<=6 consecutivos).
H5 (janela de 7 semanas) não se aplica dentro de um único mês sem
initial_state encadeado — fica coberto no cenário de ano completo (fora do
âmbito deste teste)."""

from __future__ import annotations

import datetime as dt
import time

from app.model import solve_schedule
from app.schemas import Shift, SolveStatus

from .helpers import base_request

MONTH_START = dt.date(2026, 8, 1)
MONTH_END = dt.date(2026, 8, 31)


def test_generate_month_feasible_under_60s() -> None:
    request = base_request(MONTH_START, MONTH_END)

    start = time.monotonic()
    response = solve_schedule(request)
    elapsed = time.monotonic() - start

    assert response.status == SolveStatus.FEASIBLE
    assert elapsed < 60
    assert len(response.assignments) == len(request.employees) * 31


def test_generate_month_h4_no_mixed_m_and_n() -> None:
    request = base_request(MONTH_START, MONTH_END)
    response = solve_schedule(request)
    assert response.status == SolveStatus.FEASIBLE

    per_employee_shifts: dict[int, set[Shift]] = {}
    for a in response.assignments:
        if a.shift is not None:
            per_employee_shifts.setdefault(a.employee_id, set()).add(a.shift)

    for shifts in per_employee_shifts.values():
        assert not (Shift.M in shifts and Shift.N in shifts)


def test_generate_month_h9_max_six_consecutive() -> None:
    request = base_request(MONTH_START, MONTH_END)
    response = solve_schedule(request)
    assert response.status == SolveStatus.FEASIBLE

    by_emp: dict[int, dict[dt.date, Shift | None]] = {}
    for a in response.assignments:
        by_emp.setdefault(a.employee_id, {})[a.date] = a.shift

    dates = sorted({a.date for a in response.assignments})
    for emp_days in by_emp.values():
        streak = 0
        for d in dates:
            if emp_days[d] is not None:
                streak += 1
                assert streak <= 6
            else:
                streak = 0


def test_generate_month_s7_ff_pair_each_month_in_practice() -> None:
    """H6 foi reclassificado para S7 (soft, peso 5 — ADR-0006): já não é
    obrigatório ter um par FF por mês, mas a preferência deve empurrar a
    solução nesse sentido quando o período é um mês inteiro (sem o aperto
    artificial de uma fatia isolada)."""
    request = base_request(MONTH_START, MONTH_END)
    response = solve_schedule(request)
    assert response.status == SolveStatus.FEASIBLE

    by_emp: dict[int, dict[dt.date, Shift | None]] = {}
    for a in response.assignments:
        by_emp.setdefault(a.employee_id, {})[a.date] = a.shift

    dates = sorted({a.date for a in response.assignments})
    for emp_days in by_emp.values():
        has_ff = any(
            emp_days[dates[i]] is None and emp_days[dates[i + 1]] is None
            for i in range(len(dates) - 1)
        )
        assert has_ff


def test_generate_month_s8_ff_pair_equity() -> None:
    """S8 (soft, peso 8 — ADR-0006): o nº de pares FF por pessoa no mês deve
    ficar equitativo. Não exigimos igualdade perfeita (é soft, compete com
    S1/S2/S3/S6/S7), mas a amplitude máx-mín deve ficar pequena."""
    request = base_request(MONTH_START, MONTH_END)
    response = solve_schedule(request)
    assert response.status == SolveStatus.FEASIBLE

    by_emp: dict[int, dict[dt.date, Shift | None]] = {}
    for a in response.assignments:
        by_emp.setdefault(a.employee_id, {})[a.date] = a.shift

    dates = sorted({a.date for a in response.assignments})
    ff_pair_counts = []
    for emp_days in by_emp.values():
        count = sum(
            1
            for i in range(len(dates) - 1)
            if emp_days[dates[i]] is None and emp_days[dates[i + 1]] is None
        )
        ff_pair_counts.append(count)

    assert max(ff_pair_counts) - min(ff_pair_counts) <= 2


def test_generate_month_h7_within_weekly_bounds() -> None:
    request = base_request(MONTH_START, MONTH_END)
    response = solve_schedule(request)
    assert response.status == SolveStatus.FEASIBLE

    employees_by_id = {e.id: e for e in request.employees}
    by_emp: dict[int, dict[dt.date, Shift | None]] = {}
    for a in response.assignments:
        by_emp.setdefault(a.employee_id, {})[a.date] = a.shift

    # Só semanas ISO completas (7 dias) dentro do mês têm limites diretos por contrato.
    dates = sorted({a.date for a in response.assignments})
    weeks: dict[tuple[int, int], list[dt.date]] = {}
    for d in dates:
        iso = d.isocalendar()
        weeks.setdefault((iso[0], iso[1]), []).append(d)

    tolerance = request.config.hour_bank_weekly_tolerance
    for emp_id, emp_days in by_emp.items():
        contract = employees_by_id[emp_id].contract_hours
        for week_dates in weeks.values():
            if len(week_dates) != 7:
                continue
            hours = sum(8 for d in week_dates if emp_days[d] is not None)
            assert hours <= contract + tolerance + 1e-6
            assert hours >= contract - 8 - 1e-6
