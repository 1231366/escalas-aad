from fastapi import FastAPI

from app.model import check_hard_rules, solve_schedule, swap_candidates, vacation_impact
from app.schemas import (
    GenerateRequest,
    GenerateResponse,
    SwapCandidatesRequest,
    SwapCandidatesResponse,
    VacationImpactRequest,
    VacationImpactResponse,
    ValidateRequest,
    ValidateResponse,
)

app = FastAPI(
    title="Escalas AAD — Solver",
    description="Motor de regras único (OR-Tools CP-SAT). Ver ADR-0002.",
    version="0.1.0",
)


@app.get("/health")
def health() -> dict[str, str]:
    return {"status": "ok"}


@app.post("/generate", response_model=GenerateResponse)
def generate(request: GenerateRequest) -> GenerateResponse:
    return solve_schedule(request)


@app.post("/validate", response_model=ValidateResponse)
def validate(request: ValidateRequest) -> ValidateResponse:
    violations = check_hard_rules(request)
    return ValidateResponse(valid=len(violations) == 0, violations=violations)


@app.post("/swap-candidates", response_model=SwapCandidatesResponse)
def swap_candidates_endpoint(request: SwapCandidatesRequest) -> SwapCandidatesResponse:
    return SwapCandidatesResponse(candidates=swap_candidates(request))


@app.post("/vacation-impact", response_model=VacationImpactResponse)
def vacation_impact_endpoint(request: VacationImpactRequest) -> VacationImpactResponse:
    issues = vacation_impact(request)
    return VacationImpactResponse(ok=len(issues) == 0, issues=issues)
