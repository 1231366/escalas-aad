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
- 🟡 **NO DISCO MAS SEM COMMIT** (agentes terminaram/estavam a meio quando a sessão parou):
  - **#15 notificações — TERMINADO, por commitar**: SchedulePublishedNotification, Listeners/SendSchedulePublishedNotifications, NotificationController, notification-bell.tsx (polling 30s), app-sidebar-header.tsx, rotas no fim do grupo auth, tests/Feature/NotificationTest.php (7 testes). Agente reportou 77 verdes.
  - **#9+#10 solver — QUASE/JÁ TERMINADO, por commitar**: solver/app/model.py (CP-SAT completo), main.py com /generate e /validate reais, schemas com SolverParams, testes reescritos. Estado final não confirmado — correr `cd solver && uv run pytest`.
  - **#11 grelha+geração — EM CURSO quando parou, por commitar**: app/Services/Solver/SolverClient.php, app/Services/ScheduleGridBuilder.php, Jobs, Admin\ScheduleController, páginas admin/schedules/*, rotas admin+my-schedule. Estado final desconhecido — validar contra o brief da issue #11 no GitHub.
- ⬜ Por lançar: #12 (edição manual validada), #14 (encadeamento mensal), #16 (trocas), #17 (férias), #18 (ausências), #21 (dashboards+auditoria), #22 (deploy — só falta a parte com credenciais do dono)

## RETOMA — primeiros passos da próxima sessão (ordem exata)

1. `git status` — confirmar o que está por commitar (ver lista acima).
2. `cd solver && uv run pytest -q` — se verde, o solver (#9+#10) está completo; rever `solver/app/model.py` por alto e commitar SÓ a pasta solver/ → fechar issues #9 e #10.
3. `php artisan test --compact` na raiz — avaliar o estado do #11+#15 juntos (partilham routes/web.php; commitar separados PARTE o CI).
4. Se a suite estiver verde: commit único de #11+#15 (ou dois commits mas push só no fim) → fechar issues #11 e #15. Se falhar: o que falta é do #11 (comparar com o brief na issue GitHub) — completar à mão ou relançar agente com o diff atual como contexto.
5. `npm run build && vendor/bin/pint --dirty && npm run lint` antes de qualquer push.
6. Lançar vaga seguinte (agentes Sonnet em paralelo, fronteiras de ficheiros explícitas): **#12 + #14** (dependem do #11) e **#16 + #17** (solver já livre: /swap-candidates e /vacation-impact + fluxos Laravel; ver PRD F5/F6 e ADR-0002). Depois **#18 + #21**. No fim, #22 com o dono presente.
7. Permissões: já está tudo em bypass (settings.local.json + skipDangerousModePermissionPrompt global) — não deve haver prompts.

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
