# Solver — Escalas AAD

Microserviço **stateless** que detém toda a lógica de regras (ADR-0002): geração de escalas, validação de alterações, candidatas a troca e impacto de férias, com Google OR-Tools CP-SAT.

## Correr

```bash
uv sync                                 # instala dependências
uv run uvicorn app.main:app --reload --port 8001
uv run pytest                           # testes
```

## Endpoints (contrato em `app/schemas.py`)

| Endpoint | Descrição | Estado |
|---|---|---|
| `GET /health` | Healthcheck | ✅ |
| `POST /generate` | Gera escala para um período; INFEASIBLE devolve conflitos com IDs de regras | 🔜 Fase 2 |
| `POST /validate` | Valida escala hipotética completa; devolve violações (regra+dia+pessoa) | 🔜 Fase 2 |
| `POST /swap-candidates` | Colegas com quem uma troca é válida num dado dia | 🔜 Fase 3 |
| `POST /vacation-impact` | A cobertura aguenta sem a pessoa? Onde fica em risco? | 🔜 Fase 3 |

Regras H1–H10 / S1–S6: ver `CONTEXT.md` na raiz. Parâmetros vêm no request (`RuleConfig`) — o solver não acede à base de dados.
