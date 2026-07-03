"""test_vacation_impact: teste de impacto de férias (ADR-0002).

Cenário mínimo controlado (não usa o solver): coloca a pessoa de férias
(H10) sobre uma escala dada e reporta os buracos de cobertura (H1) que isso
cria. Um caso deixa a cobertura de N a descoberto (ok=false, issue H1 no dia
certo); outro não tem impacto (ok=true).
"""

from __future__ import annotations

import datetime as dt

from app.model import vacation_impact
from app.schemas import (
    Assignment,
    CoverageRule,
    Employee,
    Regime,
    RuleConfig,
    Shift,
    VacationImpactRequest,
)

DAY = dt.date(2026, 8, 10)  # segunda-feira

ALICE_ID = 1  # vai de férias
BEATRIZ_ID = 2  # continua no turno N


def _employees() -> list[Employee]:
    return [
        Employee(id=ALICE_ID, contract_hours=40.0, regime=Regime.NOITE),
        Employee(id=BEATRIZ_ID, contract_hours=40.0, regime=Regime.NOITE),
    ]


def _base_request(coverage: list[CoverageRule]) -> VacationImpactRequest:
    return VacationImpactRequest(
        period_start=DAY,
        period_end=DAY,
        employees=_employees(),
        coverage=coverage,
        config=RuleConfig(),
        absences=[],
        initial_state=[],
        assignments=[
            Assignment(employee_id=ALICE_ID, date=DAY, shift=Shift.N),
            Assignment(employee_id=BEATRIZ_ID, date=DAY, shift=Shift.N),
        ],
        employee_id=ALICE_ID,
        start=DAY,
        end=DAY,
    )


def test_vacation_leaves_coverage_short_reports_h1() -> None:
    coverage = [CoverageRule(weekday=DAY.weekday(), shift=Shift.N, required=2)]
    request = _base_request(coverage)

    issues = vacation_impact(request)

    h1 = [v for v in issues if v.rule == "H1" and v.date == DAY]
    assert h1


def test_vacation_no_conflict_is_ok() -> None:
    coverage = [CoverageRule(weekday=DAY.weekday(), shift=Shift.N, required=1)]
    request = _base_request(coverage)

    issues = vacation_impact(request)

    assert issues == []
