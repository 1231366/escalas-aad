# ADR-0005 — Infraestrutura 100% gratuita (substitui o deploy do ADR-0001)

**Estado:** aceite · **Data:** 2026-07-02 · **Substitui:** secção de deploy/infra do ADR-0001

## Contexto

Requisito novo do dono do projeto: **nenhum serviço pago**. O Railway é pago após o trial; Redis gerido e workers dedicados também custam dinheiro. A escala real (1 organização, ~13 utilizadores) não justifica nada disso.

## Decisão

| Peça | Serviço | Plano |
|---|---|---|
| Web app Laravel | **Render** (web service, via Dockerfile — Render não tem runtime PHP nativo) | Free |
| Solver FastAPI | **Render** (web service, runtime Python nativo) | Free |
| Postgres + Storage | **Supabase** | Free |
| Email transacional | **Brevo** (300/dia) ou Resend (100/dia) | Free |
| Scheduler | **cron-job.org** a fazer ping a endpoint autenticado (`/ops/tick`) que corre o scheduler e processa a fila | Free |
| Realtime in-app | **Polling** de 30s ao endpoint de notificações (substitui Reverb/websockets) | — |
| Filas | Driver **`database`** do Laravel (substitui Redis); jobs processados no tick do cron | — |
| Domínio | O que o utilizador já tem, apontado por DNS ao Render | Já pago |
| CI | GitHub Actions (repo privado, dentro da quota grátis) | Free |

Docker desaparece do desenvolvimento local (tudo corre nativo: `php artisan serve`, `vite`, `uvicorn`); fica apenas o `Dockerfile` de produção do Laravel.

## Consequências

- Custo mensal: **0€**.
- Trade-offs aceites: cold start (~30–60s) após 15 min de inatividade nos serviços Render; notificações in-app com latência até ~30s; jobs de fila processados ao ritmo do tick (1 min) — tudo invisível para 13 utilizadores.
- Se um dia houver orçamento, subir para instâncias pagas do Render (sem spin-down) e Reverb é mudança de config/deploy, não de arquitetura.
