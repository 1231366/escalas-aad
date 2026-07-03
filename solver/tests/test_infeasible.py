"""test_infeasible_explicado: cobertura impossível -> INFEASIBLE com
conflito H1 que menciona o défice concreto."""

from __future__ import annotations

import datetime as dt

from app.model import solve_schedule
from app.schemas import CoverageRule, Shift, SolveStatus

from .helpers import base_request


def test_infeasible_impossible_coverage_reports_h1() -> None:
    request = base_request(dt.date(2026, 8, 1), dt.date(2026, 8, 7))
    # 13 pessoas exigidas num turno N, mas só há 12 AAD no total.
    request.coverage = [
        c for c in request.coverage if not (c.weekday == 0 and c.shift == Shift.N)
    ]
    request.coverage.append(CoverageRule(weekday=0, shift=Shift.N, required=13))

    response = solve_schedule(request)

    assert response.status == SolveStatus.INFEASIBLE
    assert response.conflicts
    h1_conflicts = [c for c in response.conflicts if c.rule == "H1"]
    assert h1_conflicts
    assert any("13" in c.message for c in h1_conflicts)
