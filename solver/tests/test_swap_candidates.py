"""test_swap_candidates: candidatas a troca (ADR-0002).

Cenário mínimo controlado (não usa o solver): um único dia de período, 3
colegas HIBRIDO para não colidir com H8. A requester (M) pode trocar com a
colega A (T) sem problema; a colega B tem N na véspera (initial_state) e
receberia M da requester, o que viola H3 (11h de descanso) — não deve
aparecer nas candidates. A colega C já tem o mesmo turno da requester (M) —
trocar igual por igual não conta como troca.
"""

from __future__ import annotations

import datetime as dt

from app.model import swap_candidates
from app.schemas import (
    Assignment,
    Employee,
    Regime,
    RuleConfig,
    Shift,
    SwapCandidatesRequest,
)

DAY = dt.date(2026, 8, 10)  # segunda-feira
PREV_DAY = DAY - dt.timedelta(days=1)

REQUESTER_ID = 1
COLLEAGUE_A_ID = 2  # trocável (T <-> M)
COLLEAGUE_B_ID = 3  # N na véspera -> M violaria H3
COLLEAGUE_C_ID = 4  # já tem o mesmo turno (M) da requester


def _employees() -> list[Employee]:
    return [
        Employee(id=REQUESTER_ID, contract_hours=40.0, regime=Regime.HIBRIDO),
        Employee(id=COLLEAGUE_A_ID, contract_hours=40.0, regime=Regime.HIBRIDO),
        Employee(id=COLLEAGUE_B_ID, contract_hours=40.0, regime=Regime.HIBRIDO),
        Employee(id=COLLEAGUE_C_ID, contract_hours=40.0, regime=Regime.HIBRIDO),
    ]


def _request() -> SwapCandidatesRequest:
    return SwapCandidatesRequest(
        period_start=DAY,
        period_end=DAY,
        employees=_employees(),
        coverage=[],  # H1 fora de âmbito deste teste: só interessa H3
        config=RuleConfig(),
        absences=[],
        initial_state=[
            Assignment(employee_id=COLLEAGUE_B_ID, date=PREV_DAY, shift=Shift.N),
        ],
        assignments=[
            Assignment(employee_id=REQUESTER_ID, date=DAY, shift=Shift.M),
            Assignment(employee_id=COLLEAGUE_A_ID, date=DAY, shift=Shift.T),
            Assignment(employee_id=COLLEAGUE_B_ID, date=DAY, shift=None),
            Assignment(employee_id=COLLEAGUE_C_ID, date=DAY, shift=Shift.M),
        ],
        requester_employee_id=REQUESTER_ID,
        date=DAY,
    )


def test_swap_valid_colleague_is_candidate() -> None:
    candidates = swap_candidates(_request())
    ids = {c.employee_id for c in candidates}
    assert COLLEAGUE_A_ID in ids


def test_swap_forbidden_transition_excludes_colleague() -> None:
    candidates = swap_candidates(_request())
    ids = {c.employee_id for c in candidates}
    assert COLLEAGUE_B_ID not in ids


def test_swap_identical_shift_is_not_a_candidate() -> None:
    candidates = swap_candidates(_request())
    ids = {c.employee_id for c in candidates}
    assert COLLEAGUE_C_ID not in ids


def test_swap_candidate_reports_previous_shift() -> None:
    candidates = swap_candidates(_request())
    a = next(c for c in candidates if c.employee_id == COLLEAGUE_A_ID)
    assert a.shift == Shift.T
