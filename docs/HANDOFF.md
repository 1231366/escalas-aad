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

## Estado (última atualização: FASE 1 COMPLETA ✅)

- ✅ **Fase 0** (issues #1–#3): Laravel + solver + dev.sh + CI (verde no GitHub Actions)
- ✅ **Fase 1** (issues #4–#8): schema multi-tenant completo, auth só por convite, convites com predefinições + WhatsApp + aceitação pública, config de regras (turnos/cobertura/parâmetros), perfil de trabalho + prefs de notificação. **56 testes Pest verdes.**
- ⬜ **Fase 2 A SEGUIR** (issues #9–#14): solver CP-SAT H1–H3 (#9), H4–H10+soft (#10), geração+grelha admin (#11), edição manual validada (#12), check viabilidade (#13), encadeamento mensal (#14)
- ⬜ Fase 3 (notificações #15, trocas #16, férias #17, ausências #18), Fase 4 (Excel #19, iCal #20, dashboards #21, deploy #22)

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
