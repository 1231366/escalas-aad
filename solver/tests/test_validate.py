"""test_validate: valida escalas hipotéticas diretamente (sem solver).

- escala gerada válida -> valid=true
- N->M forçado -> violação H3 com dia e pessoa
- remove uma pessoa de um M -> violação H1
- initial_state a terminar em N -> dia 1 não pode ser M nem T (fronteira H3)
"""

from __future__ import annotations

import datetime as dt

from app.model import check_hard_rules, solve_schedule
from app.schemas import Assignment, GenerateRequest, Shift, SolveStatus, ValidateRequest

from .helpers import base_request

WEEK_START = dt.date(2026, 8, 3)
WEEK_END = dt.date(2026, 8, 9)


def _to_validate_request(
    generate_request: GenerateRequest, assignments: list[Assignment]
) -> ValidateRequest:
    return ValidateRequest(**generate_request.model_dump(), assignments=assignments)


def test_validate_generated_schedule_is_valid() -> None:
    request = base_request(WEEK_START, WEEK_END)
    response = solve_schedule(request)
    assert response.status == SolveStatus.FEASIBLE

    validate_request = _to_validate_request(request, response.assignments)
    violations = check_hard_rules(validate_request)

    assert violations == []


def test_validate_detects_forbidden_transition() -> None:
    request = base_request(WEEK_START, WEEK_END)
    response = solve_schedule(request)
    assignments = list(response.assignments)

    dates = sorted({a.date for a in assignments})
    emp_id = assignments[0].employee_id
    index_by_key = {(a.employee_id, a.date): i for i, a in enumerate(assignments)}

    idx0 = index_by_key[(emp_id, dates[0])]
    idx1 = index_by_key[(emp_id, dates[1])]
    assignments[idx0] = Assignment(employee_id=emp_id, date=dates[0], shift=Shift.N)
    assignments[idx1] = Assignment(employee_id=emp_id, date=dates[1], shift=Shift.M)

    violations = check_hard_rules(_to_validate_request(request, assignments))

    h3 = [v for v in violations if v.rule == "H3"]
    assert h3
    assert any(v.employee_id == emp_id and v.date == dates[0] for v in h3)


def test_validate_detects_missing_coverage() -> None:
    request = base_request(WEEK_START, WEEK_END)
    response = solve_schedule(request)
    assignments = list(response.assignments)

    dates = sorted({a.date for a in assignments})
    target_date = dates[0]
    for i, a in enumerate(assignments):
        if a.date == target_date and a.shift == Shift.M:
            assignments[i] = Assignment(employee_id=a.employee_id, date=target_date, shift=None)
            break
    else:
        raise AssertionError("esperava pelo menos um turno M no primeiro dia")

    violations = check_hard_rules(_to_validate_request(request, assignments))

    h1 = [v for v in violations if v.rule == "H1" and v.date == target_date]
    assert h1


def test_initial_state_forbids_m_or_t_after_night() -> None:
    period_start = WEEK_START
    emp_id = 3  # Carla Nunes, HIBRIDO — pode fazer M/T/N, testa a fronteira H3
    initial_state = [
        Assignment(employee_id=emp_id, date=period_start - dt.timedelta(days=1), shift=Shift.N)
    ]
    request = base_request(period_start, WEEK_END, initial_state=initial_state)
    response = solve_schedule(request)

    assert response.status == SolveStatus.FEASIBLE
    day1 = next(
        a for a in response.assignments if a.employee_id == emp_id and a.date == period_start
    )
    assert day1.shift not in (Shift.M, Shift.T)
