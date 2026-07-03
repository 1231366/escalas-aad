"""test_generate_week: 7 dias FEASIBLE, cobertura exata, sem transições
proibidas, regimes respeitados (H1, H3, H8)."""

from __future__ import annotations

import datetime as dt

from app.model import solve_schedule
from app.schemas import Shift, SolveStatus

from .helpers import base_request, full_coverage

WEEK_START = dt.date(2026, 8, 3)  # segunda-feira
WEEK_END = dt.date(2026, 8, 9)  # domingo


def test_generate_week_feasible_and_covers_exactly() -> None:
    request = base_request(WEEK_START, WEEK_END)
    response = solve_schedule(request)

    assert response.status == SolveStatus.FEASIBLE
    assert len(response.assignments) == len(request.employees) * 7

    coverage_map = {(r.weekday, r.shift): r.required for r in full_coverage()}
    counts: dict[tuple[dt.date, Shift], int] = {}
    for a in response.assignments:
        if a.shift is not None:
            counts[(a.date, a.shift)] = counts.get((a.date, a.shift), 0) + 1

    dates = [WEEK_START + dt.timedelta(days=i) for i in range(7)]
    for d in dates:
        for shift in (Shift.M, Shift.T, Shift.N):
            expected = coverage_map[(d.weekday(), shift)]
            assert counts.get((d, shift), 0) == expected


def test_generate_week_no_forbidden_transitions() -> None:
    request = base_request(WEEK_START, WEEK_END)
    response = solve_schedule(request)
    assert response.status == SolveStatus.FEASIBLE

    by_emp_day = {(a.employee_id, a.date): a.shift for a in response.assignments}
    forbidden = {(Shift.N, Shift.M), (Shift.N, Shift.T), (Shift.T, Shift.M)}
    dates = sorted({a.date for a in response.assignments})
    employee_ids = {a.employee_id for a in response.assignments}

    for e in employee_ids:
        for i in range(len(dates) - 1):
            s1 = by_emp_day[(e, dates[i])]
            s2 = by_emp_day[(e, dates[i + 1])]
            if s1 is not None and s2 is not None:
                assert (s1, s2) not in forbidden


def test_generate_week_respects_regime() -> None:
    request = base_request(WEEK_START, WEEK_END)
    response = solve_schedule(request)
    assert response.status == SolveStatus.FEASIBLE

    employees_by_id = {e.id: e for e in request.employees}
    for a in response.assignments:
        emp = employees_by_id[a.employee_id]
        if emp.fixa_noite:
            assert a.shift in (Shift.N, None)
        if emp.regime == emp.regime.DIA:
            assert a.shift != Shift.N
        if emp.regime == emp.regime.NOITE:
            assert a.shift in (Shift.N, None)
