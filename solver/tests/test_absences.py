"""test_h10_absences: pessoa ausente fica F obrigatoriamente nesses dias."""

from __future__ import annotations

import datetime as dt

from app.model import solve_schedule
from app.schemas import Absence, SolveStatus

from .helpers import base_request


def test_h10_absence_forces_folga() -> None:
    absent_employee_id = 5  # Elsa Martins, DIA
    absence = Absence(
        employee_id=absent_employee_id,
        start=dt.date(2026, 8, 4),
        end=dt.date(2026, 8, 6),
    )
    request = base_request(dt.date(2026, 8, 3), dt.date(2026, 8, 9), absences=[absence])
    response = solve_schedule(request)

    assert response.status == SolveStatus.FEASIBLE
    for a in response.assignments:
        if a.employee_id == absent_employee_id and absence.start <= a.date <= absence.end:
            assert a.shift is None
