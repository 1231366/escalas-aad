from fastapi import FastAPI, HTTPException

from app.schemas import (
    GenerateRequest,
    GenerateResponse,
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
    raise HTTPException(status_code=501, detail="Geração implementada na Fase 2 (issue #9)")


@app.post("/validate", response_model=ValidateResponse)
def validate(request: ValidateRequest) -> ValidateResponse:
    raise HTTPException(status_code=501, detail="Validação implementada na Fase 2 (issue #10)")
