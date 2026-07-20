"""Contratos de request/response do solver (ADR-0002).

O solver é stateless: cada pedido traz os dados e a config da organização.
IDs de regras (H1–H5, H7–H10, S1–S8; H6 reclassificado para S7 — ADR-0006)
conforme CONTEXT.md na raiz do repo.
"""

from __future__ import annotations

import datetime as dt
from enum import Enum

from pydantic import BaseModel, Field


class Shift(str, Enum):
    M = "M"  # Manhã 08–16
    T = "T"  # Tarde 16–00
    N = "N"  # Noite 00–08


class Regime(str, Enum):
    DIA = "DIA"  # só M/T
    NOITE = "NOITE"  # só N
    HIBRIDO = "HIBRIDO"


class Employee(BaseModel):
    id: int
    name: str = ""
    contract_hours: float = Field(description="Horas semanais de contrato: 37.5 ou 40")
    regime: Regime = Regime.HIBRIDO
    fixa_noite: bool = False


class CoverageRule(BaseModel):
    weekday: int = Field(ge=0, le=6, description="0=segunda … 6=domingo (ISO)")
    shift: Shift
    required: int = Field(ge=0)


class RuleConfig(BaseModel):
    """Parâmetros das regras hard; nunca hardcoded no modelo (ADR-0003/0004)."""

    hour_bank_weekly_tolerance: float = 4.0  # H7
    max_consecutive_work_days: int = 6  # H9
    ff_window_weeks: int = 7  # H5
    ff_monthly: bool = True  # S7 (preferível, ex-H6 — ver ADR-0006)
    shift_hours: float = 8.0
    forbidden_transitions: list[tuple[Shift, Shift]] = [
        (Shift.N, Shift.M),
        (Shift.N, Shift.T),
        (Shift.T, Shift.M),
    ]  # H3 (matriz de descanso 11h)


class Absence(BaseModel):
    employee_id: int
    start: dt.date
    end: dt.date


class Assignment(BaseModel):
    employee_id: int
    date: dt.date
    shift: Shift | None = None  # None = folga (F)


class SolverParams(BaseModel):
    """Parâmetros de execução do CP-SAT (não fazem parte das regras de negócio)."""

    max_time_in_seconds: float = 20.0
    num_search_workers: int = 8
    relative_gap_limit: float = 0.02


class GenerateRequest(BaseModel):
    period_start: dt.date
    period_end: dt.date
    employees: list[Employee]
    coverage: list[CoverageRule]
    config: RuleConfig = RuleConfig()
    absences: list[Absence] = []
    initial_state: list[Assignment] = Field(
        default=[],
        description="Últimos dias do mês anterior, para H3/H5/H9 atravessarem a fronteira",
    )
    solver_params: SolverParams = Field(default_factory=SolverParams)


class Violation(BaseModel):
    rule: str = Field(description="ID canónico: H1…H10 ou S1…S6")
    message: str
    date: dt.date | None = None
    employee_id: int | None = None


class SolveStatus(str, Enum):
    FEASIBLE = "FEASIBLE"
    INFEASIBLE = "INFEASIBLE"
    TIMEOUT = "TIMEOUT"


class GenerateResponse(BaseModel):
    status: SolveStatus
    assignments: list[Assignment] = []
    conflicts: list[Violation] = []  # preenchido quando INFEASIBLE
    objective: float | None = None
    wall_time_s: float | None = None


class ValidateRequest(GenerateRequest):
    assignments: list[Assignment]


class ValidateResponse(BaseModel):
    valid: bool
    violations: list[Violation] = []


class SwapCandidatesRequest(ValidateRequest):
    """Pedido de candidatas a troca (ADR-0002): mesmos campos do
    ValidateRequest (inputs + escala completa do período) mais quem pede a
    troca e em que dia."""

    requester_employee_id: int
    date: dt.date


class SwapCandidate(BaseModel):
    employee_id: int
    shift: Shift | None = None  # turno que a colega tinha antes da troca


class SwapCandidatesResponse(BaseModel):
    candidates: list[SwapCandidate] = []


class VacationImpactRequest(ValidateRequest):
    """Pedido de teste de impacto de férias (ADR-0002): mesmos campos do
    ValidateRequest mais a pessoa e o intervalo de férias a simular."""

    employee_id: int
    start: dt.date
    end: dt.date


class VacationImpactResponse(BaseModel):
    ok: bool
    issues: list[Violation] = []
