import datetime as dt

from fastapi.testclient import TestClient

from app.main import app
from app.schemas import CoverageRule, Employee, GenerateRequest, Shift

client = TestClient(app)


def test_health() -> None:
    response = client.get("/health")
    assert response.status_code == 200
    assert response.json() == {"status": "ok"}


def _valid_generate_payload() -> dict:
    request = GenerateRequest(
        period_start=dt.date(2026, 8, 1),
        period_end=dt.date(2026, 8, 31),
        employees=[Employee(id=1, contract_hours=40)],
        coverage=[CoverageRule(weekday=0, shift=Shift.M, required=4)],
    )
    return request.model_dump(mode="json")


def test_generate_stub_returns_501() -> None:
    response = client.post("/generate", json=_valid_generate_payload())
    assert response.status_code == 501


def test_generate_rejects_malformed_payload() -> None:
    response = client.post("/generate", json={"period_start": "not-a-date"})
    assert response.status_code == 422
