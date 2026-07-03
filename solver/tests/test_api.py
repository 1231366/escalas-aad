import datetime as dt

from fastapi.testclient import TestClient

from app.main import app
from app.schemas import (
    Assignment,
    CoverageRule,
    Employee,
    GenerateRequest,
    Regime,
    Shift,
    SwapCandidatesRequest,
    VacationImpactRequest,
)

client = TestClient(app)


def test_health() -> None:
    response = client.get("/health")
    assert response.status_code == 200
    assert response.json() == {"status": "ok"}


def _minimal_feasible_payload() -> dict:
    # Sem regras de cobertura, uma única pessoa: toda a gente de folga é
    # trivialmente viável. Serve para validar o "fio condutor" da API.
    request = GenerateRequest(
        period_start=dt.date(2026, 8, 3),  # segunda-feira: semana ISO completa
        period_end=dt.date(2026, 8, 9),
        employees=[Employee(id=1, contract_hours=40)],
        coverage=[],
    )
    return request.model_dump(mode="json")


def test_generate_minimal_feasible() -> None:
    response = client.post("/generate", json=_minimal_feasible_payload())
    assert response.status_code == 200
    body = response.json()
    assert body["status"] == "FEASIBLE"
    assert len(body["assignments"]) == 7


def test_generate_rejects_malformed_payload() -> None:
    response = client.post("/generate", json={"period_start": "not-a-date"})
    assert response.status_code == 422


def test_swap_candidates_endpoint_smoke() -> None:
    day = dt.date(2026, 8, 10)
    request = SwapCandidatesRequest(
        period_start=day,
        period_end=day,
        employees=[
            Employee(id=1, contract_hours=40.0, regime=Regime.HIBRIDO),
            Employee(id=2, contract_hours=40.0, regime=Regime.HIBRIDO),
        ],
        coverage=[],
        assignments=[
            Assignment(employee_id=1, date=day, shift=Shift.M),
            Assignment(employee_id=2, date=day, shift=Shift.T),
        ],
        requester_employee_id=1,
        date=day,
    )
    response = client.post("/swap-candidates", json=request.model_dump(mode="json"))
    assert response.status_code == 200
    body = response.json()
    assert body["candidates"] == [{"employee_id": 2, "shift": "T"}]


def test_vacation_impact_endpoint_smoke() -> None:
    day = dt.date(2026, 8, 10)
    request = VacationImpactRequest(
        period_start=day,
        period_end=day,
        employees=[
            Employee(id=1, contract_hours=40.0, regime=Regime.NOITE),
            Employee(id=2, contract_hours=40.0, regime=Regime.NOITE),
        ],
        coverage=[CoverageRule(weekday=day.weekday(), shift=Shift.N, required=2)],
        assignments=[
            Assignment(employee_id=1, date=day, shift=Shift.N),
            Assignment(employee_id=2, date=day, shift=Shift.N),
        ],
        employee_id=1,
        start=day,
        end=day,
    )
    response = client.post("/vacation-impact", json=request.model_dump(mode="json"))
    assert response.status_code == 200
    body = response.json()
    assert body["ok"] is False
    assert any(issue["rule"] == "H1" for issue in body["issues"])
