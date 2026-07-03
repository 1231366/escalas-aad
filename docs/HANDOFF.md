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

## Estado (última atualização: sessão interrompida a meio da Fase 2/3 — LER "Retoma" abaixo)

- ✅ **Fase 0** (#1–#3), **Fase 1** (#4–#8) — completas e commitadas
- ✅ **#13 viabilidade** (commitado), **#19 Excel + #20 iCal** (commitados) — antecipados
- ✅ **Dockerfile de produção + .dockerignore** commitados (parte técnica do #22)
- ✅ **#11 geração+grelha e #15 notificações** — commitados e issues fechadas (suite Laravel: 86 verdes)
- 🟡 **NO DISCO MAS SEM COMMIT — só a pasta solver/** (#9+#10): o agente estava a terminar quando a sessão parou. Já existem: solver/app/model.py (CP-SAT), main.py com /generate e /validate reais, schemas com SolverParams, testes reescritos. Estado final NÃO confirmado.
- ⬜ Por lançar: #12 (edição manual validada), #14 (encadeamento mensal), #16 (trocas), #17 (férias), #18 (ausências), #21 (dashboards+auditoria), #22 (deploy — falta só a parte com credenciais do dono; Dockerfile já feito)

## Atualização 2026-07-03 — quase tudo fechado, faltam trocas (#16) e confirmar ausências (#18)

Fechadas nesta ronda: #9, #10 (solver completo, 26 testes), #12 (edição de células), #14 (encadeamento mensal — coberto pelo #11), #17 (férias), #21 (dashboards+auditoria). Suite Laravel: **113 testes verdes**. Regra de negócio mudou (ADR-0006): FF mensal é preferência (S7), só a janela de 7 semanas é obrigatória (H5), e há equidade do nº de FF entre pessoas (S8) — resolveu a infeasibilidade da semana isolada.

**#18 ausências — código presente mas SEM TESTES**, o agente foi cortado por limite de sessão a meio: existem `AbsenceController`, `AbsenceGapCalculator`, `PartialReoptimizer`, `ReoptimizeScheduleJob`, migration de colunas, página `admin/absences/index.tsx`, rotas registadas. Falta: `tests/Feature/AbsenceTest.php` e validar manualmente o fluxo (criar ausência → ver buracos → re-otimizar). Sintaxe PHP confirmada OK.

**#16 trocas — INCOMPLETO de verdade**, cortado muito cedo: existem só `SwapController`, `Admin\SwapController`, `StoreSwapRequest`, notificações Swap*, `Services/Swap/`. FALTAM: rotas em `routes/web.php`, páginas React (`swaps/create`, `swaps/index`, `admin/swaps/index`), botão "Trocar" em `schedule/my.tsx`, e `tests/Feature/SwapTest.php`. O solver já tem `/swap-candidates` pronto e testado. Retomar relançando um agente com o brief original da issue #16 (está no histórico) + "revê o que já existe em app/Http/Controllers/{Swap,Admin/Swap}Controller.php e app/Services/Swap/ antes de começar".

## RETOMA — primeiros passos da próxima sessão (ordem exata)

1. `cd solver && uv run pytest -q` — se verde: rever solver/app/model.py por alto, commitar a pasta solver/ e fechar issues #9 e #10. Se falhar/incompleto (comparar com os critérios das issues #9/#10 no GitHub): relançar UM agente Sonnet com o diff atual como contexto para terminar, validar, commitar.
2. Smoke test de integração real (nada disto foi testado ponta-a-ponta ainda): `scripts/dev.sh`, login admin@demo.test/password, criar escala de mês, gerar (o job chama o solver real em :8001 — para processar a fila em dev: `php artisan queue:work --stop-when-empty`), verificar grelha FEASIBLE, publicar, ver /escala como aad1@demo.test, sino de notificações, export Excel, feed iCal.
3. Lançar vaga seguinte (agentes Sonnet paralelos, fronteiras explícitas, validação completa obrigatória, sem commits pelos agentes): **#12 + #14** e, como o solver fica livre, **#16 (trocas: /swap-candidates no solver + fluxo Laravel completo com notificações a ambas+admin) + #17 (férias: /vacation-impact + decisão admin)** — ver PRD F5/F6, ADR-0002 e CONTEXT.md. Depois **#18 + #21**. Por fim #22 com o dono (Render+Supabase+DNS).
4. Permissões: tudo em bypass (settings.local.json + skipDangerousModePermissionPrompt global) — zero prompts esperados.

## Modo de trabalho: ORQUESTRADOR

Eu (sessão principal) não escrevo código diretamente: parto cada issue em briefs precisos e lanço **agentes Sonnet** (Agent tool, model sonnet, background), só os necessários, com: contexto+ficheiros a ler, o que construir, fronteiras (que ficheiros NÃO tocar p/ evitar colisões entre agentes paralelos), validação obrigatória (npm run build + php artisan test --compact TODA verde + pint --dirty + npm run lint; solver: uv run pytest) e proibição de commit. Eu revejo os diffs, corro a suite final, commito (SEM Co-Authored-By), fecho a issue com referência ao commit e atualizo este ficheiro.

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
