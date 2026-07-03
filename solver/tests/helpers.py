"""Cenário de teste da folha real (12 AAD), espelhando o DemoSeeder Laravel.

2 NOITE fixas, 2 HIBRIDO (elegíveis noite), 8 DIA. Contratos: aad4, aad7 e
aad10 (Diana, Gabriela, Joana) são 37h30; as restantes 40h. Cobertura
4M/3T/2N todos os dias, tolerância de banco de horas 4h/semana.
"""

from __future__ import annotations

import datetime as dt

from app.schemas import (
    Absence,
    Assignment,
    CoverageRule,
    Employee,
    GenerateRequest,
    Regime,
    RuleConfig,
    Shift,
)

# (id, nome, regime, fixa_noite, contract_hours) — ordem = aad1..aad12
TEAM = [
    (1, "Alice Fontes", Regime.NOITE, True, 40.0),
    (2, "Beatriz Ramos", Regime.NOITE, True, 40.0),
    (3, "Carla Nunes", Regime.HIBRIDO, False, 40.0),
    (4, "Diana Costa", Regime.HIBRIDO, False, 37.5),
    (5, "Elsa Martins", Regime.DIA, False, 40.0),
    (6, "Filipa Sousa", Regime.DIA, False, 40.0),
    (7, "Gabriela Pinto", Regime.DIA, False, 37.5),
    (8, "Helena Silva", Regime.DIA, False, 40.0),
    (9, "Inês Ferreira", Regime.DIA, False, 40.0),
    (10, "Joana Lopes", Regime.DIA, False, 37.5),
    (11, "Luísa Baptista", Regime.DIA, False, 40.0),
    (12, "Marta Rocha", Regime.DIA, False, 40.0),
]


def real_team() -> list[Employee]:
    return [
        Employee(id=i, name=name, contract_hours=hours, regime=regime, fixa_noite=fixa)
        for i, name, regime, fixa, hours in TEAM
    ]


def full_coverage() -> list[CoverageRule]:
    rules = []
    for weekday in range(7):
        rules.append(CoverageRule(weekday=weekday, shift=Shift.M, required=4))
        rules.append(CoverageRule(weekday=weekday, shift=Shift.T, required=3))
        rules.append(CoverageRule(weekday=weekday, shift=Shift.N, required=2))
    return rules


def base_request(
    period_start: dt.date,
    period_end: dt.date,
    *,
    absences: list[Absence] | None = None,
    initial_state: list[Assignment] | None = None,
) -> GenerateRequest:
    return GenerateRequest(
        period_start=period_start,
        period_end=period_end,
        employees=real_team(),
        coverage=full_coverage(),
        # NOTA: ADR-0003 propõe 4h/semana por omissão, mas com a equipa mista
        # (9x40h + 3x37h30) e cobertura 4/3/2 todos os dias, a procura é de 63
        # turnos-pessoa/semana contra uma oferta máxima de 60 a 4h de
        # tolerância (défice de -3/semana, tal como o próprio ADR-0003
        # documenta). É preciso 8h/semana para desbloquear o 6º turno das
        # pessoas a 40h e fechar a conta (oferta 69 >= procura 63). Ver
        # docs/adr/0003 e a mensagem de commit para detalhe do cálculo.
        config=RuleConfig(hour_bank_weekly_tolerance=8.0),
        absences=absences or [],
        initial_state=initial_state or [],
    )
