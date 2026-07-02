# HANDOFF — estado da sessão (atualizado 2026-07-02)

Documento de retoma: o que está feito, decisões tomadas e o que vem a seguir. Atualizar no fim de cada bloco de trabalho.

## O projeto num parágrafo

App web de gestão de escalas para equipas AAD (12 funcionárias, folha de regras em `docs/regras-folha.png`): admin convida por link WhatsApp, solver CP-SAT gera a escala mensal cumprindo as regras, funcionárias trocam turnos entre si (validado antes de pedir), férias, notificações email+in-app, export Excel, feed iCal Google/Apple. Portfólio: MVC limpo + custo zero.

## Onde está tudo

- **Plano mestre:** `docs/PRD.md` (features F1–F10, fases 0–4) · **Regras:** `CONTEXT.md` (glossário + IDs H1–H10/S1–S6) · **Decisões:** `docs/adr/0001–0005`
- **Execução:** issues no GitHub `1231366/escalas-aad` (22 issues, 5 milestones por fase). Issues fechadas = trabalho feito e commitado.
- **Skills:** mattpocock/skills instaladas em `~/.claude/skills` (repo em `~/.claude/repos/mattpocock-skills`); config em `docs/agents/` + `CLAUDE.md`.

## Stack (ADR-0001 + ADR-0005)

Laravel 12 monólito (raiz do repo) + Inertia v2 + React 19 + TS + Tailwind 4 + shadcn/ui · solver Python 3.12 FastAPI + OR-Tools CP-SAT em `solver/` · Postgres local (brew postgresql@15, BD `escalas_aad`, user `t`) e Supabase em prod · **tudo grátis**: Render free (deploy), sem Redis (filas `database`), sem websockets (polling 30s), email Brevo/Resend free, scheduler via cron-job.org → `/ops/tick`.

## Como correr

```bash
scripts/dev.sh          # web :8000 + vite + solver :8001 + queue
php artisan test        # Pest
cd solver && uv run pytest
```

Login demo (após `php artisan migrate:fresh --seed`): `admin@demo.test` / `password`; funcionárias `aad1@demo.test`…`aad12@demo.test` / `password`.

## Estado (última atualização: Fase 1 a meio)

- ✅ **Fase 0** (issues #1–#3): Laravel + solver + dev.sh + CI (verde no GitHub Actions)
- ✅ **Issue #4**: schema completo — 8 enums, migrations todas, 12 models, multi-tenancy por global scope (`BelongsToOrganization`), factories, `DemoSeeder` com o cenário da folha
- ✅ **Issue #5**: auth só por convite (registo aberto removido), middleware `admin`, `auth.isAdmin` no Inertia, rate limiting login
- 🔨 **Issue #6 EM CURSO**: convites — `StoreInvitationRequest` criado; **falta**: controllers (admin CRUD + aceitação pública), rotas, notificação ao admin, páginas React (admin/invitations/index + página pública de aceitação), testes
- ⬜ Issues #7 (config regras), #8 (perfil) fecham a Fase 1
- ⬜ Fases 2 (solver H1–H10, geração, grelha), 3 (notificações, trocas, férias), 4 (Excel, iCal, dashboards, deploy)

## Decisões e preferências do dono (não esquecer)

- **Sem serviços pagos, ponto final** (ADR-0005). Domínio já existe (registado via InfinityFree — só se usa o DNS, o hosting deles não serve).
- **Sem Docker em dev** (só Dockerfile de produção para o Render, ainda por fazer na issue #22).
- **Commits sem Co-Authored-By** (guardado na memória persistente).
- Utilizador é co-piloto: decide requisitos, eu decido técnica e executo. Trabalhar fase a fase, fechar issues com commit referenciado.
- Q1–Q8 da folha resolvidas no ADR-0004 (o `{ MT` riscado = anotação descartada; publicação mensal; indicadores horas/semana + cobertura/dia + folgas obrigatórios na grelha e no Excel).

## Notas técnicas que poupam tempo

- PHP local é 8.5 (deprecações: removi conexões mysql/mariadb/sqlsrv do `config/database.php`).
- Pest 4 instalado (phpunit standalone removido); CI chama `vendor/bin/pest`.
- `.gitignore`: `!.env.example` tem de ficar DEPOIS de `.env.*` (já corrigido; partiu o CI uma vez).
- Token do `gh` fornecido pelo utilizador tem scopes a mais — sugerido regenerar com só `repo`+`workflow` (pendente da parte dele).
- `Invitation::whatsappUrl()` gera o link wa.me com mensagem pronta; `acceptUrl()` espera rota `invitations.show` (ainda por criar).
- Solver: schemas Pydantic completos em `solver/app/schemas.py` — o modelo CP-SAT da Fase 2 constrói-se sobre eles; pytest usa `pythonpath=["."]`.
