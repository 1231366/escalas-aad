#!/usr/bin/env bash
set -euo pipefail

# Setup único do GitHub para o escalas-aad:
#   1. cria o repositório privado e faz push
#   2. cria as labels de triage + tipos
#   3. cria os milestones (Fases 0–4)
#   4. cria as issues do plano (vertical slices), ligadas aos milestones
# Requisitos: gh autenticado (gh auth status) e correr na raiz do repo.

REPO_NAME="escalas-aad"

if ! gh auth status >/dev/null 2>&1; then
  echo "erro: gh não está autenticado. Corre: gh auth login -h github.com" >&2
  exit 1
fi

OWNER="$(gh api user --jq .login)"
REPO="$OWNER/$REPO_NAME"

# ── 1. Repo ──────────────────────────────────────────────────────────────────
if gh repo view "$REPO" >/dev/null 2>&1; then
  echo "repo $REPO já existe — a saltar criação"
  git remote get-url origin >/dev/null 2>&1 || git remote add origin "https://github.com/$REPO.git"
else
  gh repo create "$REPO" --private --source . --remote origin \
    --description "Geração e gestão de escalas para equipas AAD — solver CP-SAT, trocas validadas, convites WhatsApp, export Excel, iCal"
fi
git push -u origin HEAD

# ── 2. Labels ────────────────────────────────────────────────────────────────
label() { gh label create "$1" --color "$2" --description "$3" --force -R "$REPO"; }
label "needs-triage"     "d93f0b" "Maintainer needs to evaluate this issue"
label "needs-info"       "fbca04" "Waiting on reporter for more information"
label "ready-for-agent"  "0e8a16" "Fully specified, ready for an AFK agent"
label "ready-for-human"  "1d76db" "Requires human implementation"
label "wontfix"          "cccccc" "Will not be actioned"
label "type:feature"     "a2eeef" "Nova funcionalidade"
label "type:infra"       "c5def5" "Infraestrutura, CI, deploy"
label "solver"           "5319e7" "Toca no microserviço solver (Python/CP-SAT)"

# ── 3. Milestones ────────────────────────────────────────────────────────────
ms() {
  gh api "repos/$REPO/milestones" -f title="$1" -f description="$2" --jq .number 2>/dev/null \
    || gh api "repos/$REPO/milestones" --jq ".[] | select(.title==\"$1\") | .number"
}
M0=$(ms "Fase 0 — Fundação" "Esqueletos Laravel+Inertia e solver FastAPI, Docker Compose, CI")
M1=$(ms "Fase 1 — Contas e organização" "Auth, convites WhatsApp, perfis, configuração de regras")
M2=$(ms "Fase 2 — Solver e geração" "CP-SAT H1–H10 + S1–S6, geração mensal, grelha, viabilidade")
M3=$(ms "Fase 3 — Alterações" "Notificações, trocas entre funcionárias, férias e ausências")
M4=$(ms "Fase 4 — Experiência" "Excel, iCal, dashboards, auditoria, deploy produção")

# ── 4. Issues ────────────────────────────────────────────────────────────────
issue() { # $1=milestone $2=labels $3=title $4=body
  gh issue create -R "$REPO" --milestone "$1" --label "$2" --title "$3" --body "$4"
}

issue "Fase 0 — Fundação" "type:infra,ready-for-agent" \
"Esqueleto Laravel 11 + Inertia + React + TypeScript" \
"Criar a app Laravel 11 (PHP 8.3) com Inertia.js, React 18, TypeScript, Tailwind e shadcn/ui, em \`app/\` na raiz do repo. Incluir Pint + Larastan + ESLint/Prettier e Pest configurado.

**Aceitação:** \`composer test\` e \`npm run build\` verdes; página inicial Inertia a renderizar; estrutura MVC documentada no README.
Refs: PRD §6, ADR-0001, CONTEXT.md."

issue "Fase 0 — Fundação" "type:infra,solver,ready-for-agent" \
"Esqueleto do solver FastAPI + OR-Tools" \
"Criar o microserviço \`solver/\` (Python 3.12, FastAPI, OR-Tools CP-SAT): healthcheck \`GET /health\`, esqueleto de \`POST /generate\` e \`POST /validate\` (schemas Pydantic conforme ADR-0002, resposta stub), pytest configurado com 1 teste.

**Aceitação:** \`pytest\` verde; \`uvicorn\` serve; contrato de request/response documentado em \`solver/README.md\`.
Refs: ADR-0002."

issue "Fase 0 — Fundação" "type:infra,ready-for-agent" \
"Docker Compose + CI GitHub Actions" \
"Docker Compose de desenvolvimento (app, solver, postgres, redis, mailpit) e workflow de CI que corre lint+testes do Laravel e do solver em paralelo.

**Aceitação:** \`docker compose up\` sobe tudo; CI verde no PR.
Refs: PRD §6."

issue "Fase 1 — Contas e organização" "type:feature,ready-for-agent" \
"Schema completo: migrations + models multi-tenant" \
"Migrations e Eloquent Models para todas as tabelas do PRD §4 (organizations, users, invitations, employees, shift_types, coverage_rules, rule_configs, schedules, shift_assignments, swap_requests, vacation_requests, absences, audit_logs), com factories, seeders de dev (1 org, admin, 12 funcionárias) e global scope/trait de tenant por organization_id.

**Aceitação:** migrations correm; testes de factories e do scoping por tenant verdes.
Refs: PRD §4, docs/01-planeamento.md §6."

issue "Fase 1 — Contas e organização" "type:feature,ready-for-agent" \
"Auth: login, recuperação de password, papéis e policies" \
"Sanctum (sessão web Inertia): login, logout, recuperação de password por email, rate limiting. Sem registo aberto — contas nascem por convite. Papéis ADMIN/EMPLOYEE com policies; layout autenticado com navegação por papel.

**Aceitação:** testes Pest cobrem login/logout/reset/acessos por papel; utilizador de outra org nunca vê dados alheios.
Refs: PRD F1."

issue "Fase 1 — Contas e organização" "type:feature,ready-for-agent" \
"Convites por link com predefinições + partilha WhatsApp" \
"CRUD de convites (admin): email, papel, regime (DIA/NOITE/HIBRIDO), contrato (37h30/40h), flag fixa_noite; token opaco, uso único, expira em 7 dias; reenviar/revogar; listagem com estados. Página pública de aceitação (branding da org, define password, perfil pré-preenchido). Botão wa.me com mensagem de convite pronta + envio por email.

**Aceitação:** fluxo completo testado (criar → aceitar → conta criada com regime certo; expirado/revogado rejeitados); admin notificado quando o convite é aceite.
Refs: PRD F2, CONTEXT.md (Regime, Convite)."

issue "Fase 1 — Contas e organização" "type:feature,ready-for-agent" \
"Configuração de regras: turnos, cobertura e rule_config" \
"Ecrãs admin para turnos (M/T/N e horários), cobertura por turno×dia-da-semana e parâmetros de regras (tolerância banco de horas, máx. dias consecutivos, janelas FF). Seeds com os valores da folha (4M/3T/2N, defaults do ADR-0003/0004).

**Aceitação:** alterações persistem e ficam disponíveis para o payload do solver; validação de inputs; testes verdes.
Refs: PRD F3, ADR-0003, ADR-0004."

issue "Fase 1 — Contas e organização" "type:feature,ready-for-agent" \
"Perfil da funcionária" \
"Página de perfil: dados pessoais, password, regime/contrato (leitura; só admin edita), preferências de notificação (email por tipo de evento).

**Aceitação:** testes de atualização e de autorização (funcionária não altera o próprio contrato) verdes.
Refs: PRD F1, F7."

issue "Fase 2 — Solver e geração" "type:feature,solver,ready-for-agent" \
"Solver v0: H1–H3, gerar uma semana válida" \
"Implementar no CP-SAT as variáveis x[e,d,s] e as hard H1 (cobertura), H2 (1 turno/dia), H3 (matriz de descanso 11h). \`POST /generate\` devolve uma semana válida para 12 funcionárias com a cobertura da folha; se infeasible, devolve as restrições em conflito com IDs canónicos.

**Aceitação:** pytest valida cobertura exata e ausência de transições proibidas em várias seeds; infeasible explicado quando a cobertura é impossível.
Refs: ADR-0002, CONTEXT.md (IDs), docs/03-prompt-desenvolvimento.md."

issue "Fase 2 — Solver e geração" "type:feature,solver,ready-for-agent" \
"Solver v1: H4–H10 + soft S1–S6, mês completo" \
"Adicionar incrementalmente H4 (M×N mês), H5/H6 (FF por 7 semanas e por mês), H7 (carga=contrato±banco de horas, ADR-0003), H8 (fixas noite), H9 (≤6 dias), H10 (ausências), e a função objetivo com S1–S6. Gerar um mês completo <60s. Implementar \`POST /validate\` (violações com regra+dia+pessoa) sobre o mesmo modelo.

**Aceitação:** pytest com cenários por regra (cada H tem teste que a viola e é apanhada); mês de 12 pessoas gera FEASIBLE com os defaults do ADR-0003.
Refs: ADR-0002, ADR-0003, ADR-0004."

issue "Fase 2 — Solver e geração" "type:feature,ready-for-agent" \
"Geração e publicação da escala (grelha admin)" \
"Job em fila que chama o solver e grava DRAFT; grelha pessoas×dias (cores por turno, responsive) com os indicadores da folha: horas/semana por pessoa, colaboradoras por turno/dia, nº de folgas; publicar → notifica equipa; estados DRAFT/PUBLISHED/ARCHIVED; infeasible mostrado com explicação por regra.

**Aceitação:** fluxo gerar→rever→publicar testado; funcionária só vê escalas publicadas; indicadores corretos.
Refs: PRD F4, ADR-0004 (requisito da folha)."

issue "Fase 2 — Solver e geração" "type:feature,ready-for-agent" \
"Edição manual de células com revalidação" \
"Admin edita células da DRAFT (atribuir turno/folga); cada edição passa por \`POST /validate\` e violações aparecem inline (regra + explicação); origem=MANUAL.

**Aceitação:** edição que viola H3 é bloqueada com mensagem clara; edição válida persiste; testes verdes.
Refs: PRD F4, ADR-0002."

issue "Fase 2 — Solver e geração" "type:feature,ready-for-agent" \
"Check de viabilidade" \
"Cartão no dashboard admin: oferta (contratos+tolerância) vs procura (cobertura) de turnos/semana, com défice destacado e sugestões (baixar cobertura fim de semana, subir tolerância, +1 pessoa). Bloqueio brando antes de gerar quando o défice é negativo.

**Aceitação:** os números batem com docs/01-planeamento.md §2 para o cenário de 12×40h (−3/semana); testes verdes.
Refs: ADR-0003."

issue "Fase 2 — Solver e geração" "type:feature,solver,ready-for-agent" \
"Encadeamento mensal (continuidade entre meses)" \
"Gerar um mês passando os últimos dias do mês anterior como condição inicial (H3/H5/H9 atravessam a fronteira). Comando/ação para gerar o mês seguinte a partir do publicado.

**Aceitação:** pytest com fronteira de mês: sem N→M no dia 1, janela FF de 7 semanas contada através da fronteira.
Refs: ADR-0002, docs/01-planeamento.md §7.3."

issue "Fase 3 — Alterações" "type:feature,ready-for-agent" \
"Infraestrutura de notificações (email + in-app realtime)" \
"Laravel Notifications multi-canal: mail (Resend, templates com layout próprio) + database + broadcast (Reverb). Sino no header com contagem não lida em tempo real, lista, marcar como lida. Preferências por utilizador respeitadas.

**Aceitação:** notificação de teste chega por email (mailpit em dev) e aparece no sino sem refresh; prefs desligam email; testes verdes.
Refs: PRD F7."

issue "Fase 3 — Alterações" "type:feature,solver,ready-for-agent" \
"Trocas entre funcionárias (fluxo completo)" \
"1) \`POST /swap-candidates\` no solver: dado um assignment, devolve colegas com quem a troca mantém todas as hard. 2) UI: funcionária escolhe turno seu → vê candidatas → cria pedido. 3) Alvo aceita/recusa; admin notificado de tudo; aprovação do admin opcional por config da org. 4) Antes de aplicar, revalidação da escala completa; aplicar troca marca origem=TROCA e notifica ambas por email + in-app.

**Aceitação:** testes cobrem candidatas corretas (troca que violaria H3 não aparece), corrida (escala mudou entre pedido e aceitação → revalida e rejeita com explicação), notificações a ambas + admin em cada transição.
Refs: PRD F5, ADR-0002, CONTEXT.md (Candidatas a troca)."

issue "Fase 3 — Alterações" "type:feature,solver,ready-for-agent" \
"Férias: pedido, impacto na cobertura, decisão" \
"\`POST /vacation-impact\` no solver (cobertura aguenta sem a pessoa? onde fica em risco?); funcionária pede intervalo; admin decide vendo o impacto; aprovação gera folgas origem=FERIAS e notifica a funcionária.

**Aceitação:** pedido que deixa N descoberto mostra aviso ao admin; fluxo aprovar/recusar testado com notificações.
Refs: PRD F6, ADR-0002."

issue "Fase 3 — Alterações" "type:feature,ready-for-agent" \
"Ausências e re-otimização parcial" \
"Admin regista baixa/falta; sistema marca os dias, avisa dos buracos de cobertura e oferece re-otimização parcial do resto do período (mantendo o passado intacto).

**Aceitação:** re-otimização não altera dias anteriores a hoje; buracos sinalizados; testes verdes.
Refs: PRD F6."

issue "Fase 4 — Experiência" "type:feature,ready-for-agent" \
"Exportação Excel da escala" \
"Export do mês via maatwebsite/excel: grelha pessoas×dias com cores por turno, totais por pessoa (horas/semana, folgas, fins de semana) e folha-resumo cobertura por turno/dia. Download + cópia opcional no Supabase Storage.

**Aceitação:** ficheiro abre no Excel/Google Sheets com formatação; totais batem com a grelha; teste de geração verde.
Refs: PRD F8, ADR-0004 (indicadores da folha)."

issue "Fase 4 — Experiência" "type:feature,ready-for-agent" \
"Feed iCal + botões Google/Apple Calendar" \
"Feed \`/calendar/{token}.ics\` por funcionária (token revogável): turnos publicados com horas certas, atualiza com trocas/republicações; VTIMEZONE Europe/Lisbon; página 'Sincronizar calendário' com botões/instruções Google e Apple e regenerar token.

**Aceitação:** feed valida (RFC 5545), subscrição no Google Calendar mostra turnos; troca aplicada reflete-se no feed; testes verdes.
Refs: PRD F9."

issue "Fase 4 — Experiência" "type:feature,ready-for-agent" \
"Dashboards (admin + funcionária) e auditoria" \
"Dashboard admin: estado do mês, pedidos pendentes, viabilidade, gráficos de equidade (horas, fins de semana, folgas, saldo banco de horas por pessoa). Dashboard funcionária: próximo turno, semana, pedidos. audit_log em todas as mutações relevantes com viewer simples para admin.

**Aceitação:** números de equidade batem com a escala publicada; ações de troca/edição aparecem no audit log; testes verdes.
Refs: PRD F10."

issue "Fase 4 — Experiência" "type:infra,ready-for-human" \
"Deploy produção: Railway + Supabase + domínio" \
"Serviços Railway (app com queue worker + scheduler, solver, redis), Postgres+Storage no Supabase, variáveis de ambiente documentadas, domínio do utilizador apontado por DNS, HTTPS, Reverb em produção, deploy automático por push a main.

**Aceitação:** app acessível no domínio próprio com fluxo completo (convite→geração→troca→export) a funcionar em produção.
Refs: ADR-0001, PRD §6. (ready-for-human: precisa de credenciais/DNS do dono.)"

echo
echo "✔ Setup completo: https://github.com/$REPO"
