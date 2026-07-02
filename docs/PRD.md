# PRD — Escalas AAD

**Versão 1.0 · 2026-07-02 · Fonte de regras:** `docs/regras-folha.png` + ADR-0004 · **Stack:** ADR-0001 · **Motor de regras:** ADR-0002 · **Viabilidade:** ADR-0003

---

## 1. Visão

Aplicação web (mobile-ready) onde uma organização de cuidados gere as escalas mensais da sua equipa de AAD: o admin convida as funcionárias por link (WhatsApp), o sistema **gera** a escala do mês com um constraint solver que garante todas as regras laborais e operacionais, as funcionárias consultam o seu horário, **trocam turnos entre si** com validação prévia, pedem férias, recebem notificações por email e in-app, e sincronizam o horário com o Google/Apple Calendar. O admin publica, ajusta e exporta para Excel.

Objetivo secundário explícito: ser peça de portfólio — arquitetura MVC limpa, solver isolado, código exemplar, UI polida.

## 2. Personas

- **Admin** (diretora técnica): configura regras e cobertura, convida equipa, gera/edita/publica escalas, aprova trocas e férias, exporta.
- **Funcionária AAD**: vê o seu horário, pede trocas e férias, gere o perfil e as preferências, subscreve o calendário.

## 3. Funcionalidades

### F1 — Autenticação e contas
Registo por convite (sem registo aberto), login email+password, recuperação de password, sessões Sanctum (web) + tokens API (mobile futuro). Papéis `ADMIN` / `EMPLOYEE`, multi-tenant por `organization_id`. Página de perfil (dados, password, preferências de notificação).

### F2 — Convites por link (WhatsApp)
Admin cria convite com **email, papel e regime predefinidos** — `DIA` (só M/T), `NOITE` (só N), `HIBRIDO` — e opcionalmente contrato (37h30/40h) e flag fixa noite. O sistema gera link com token (expira em 7 dias, uso único) e um **texto de convite pronto para WhatsApp** (botão `wa.me` com mensagem bonita + link, e página de aterragem com branding da organização). Ao aceitar, a funcionária define password e completa o perfil já pré-preenchido. Admin vê estado dos convites (pendente/aceite/expirado) e pode reenviar/revogar.

### F3 — Configuração de regras (admin)
Turnos (M/T/N, horários), cobertura por turno **por dia da semana**, parâmetros das regras (tolerância banco de horas, máx. dias consecutivos, janelas FF), tudo em `rule_config`/`coverage_rules` — nunca hardcoded. Ecrã de **check de viabilidade**: oferta vs procura de turnos/semana com avisos e sugestões (ADR-0003) antes de gerar.

### F4 — Geração de escala (núcleo)
Admin escolhe o mês → job em fila chama o solver (`POST /generate`) → escala `DRAFT` na grelha pessoas×dias (cores por turno) → admin revê, edita células manualmente (cada edição revalidada via `POST /validate`) → **publica**. Publicação notifica toda a equipa (email + in-app). Se *infeasible*, a UI explica que regras/dias estão em conflito, por ID canónico. Grelha mostra os indicadores da folha: horas/semana por pessoa, colaboradoras por turno/dia, nº de folgas.

### F5 — Trocas entre funcionárias
1. A funcionária escolhe um turno seu → o sistema mostra **com quem pode trocar** nesse dia (`POST /swap-candidates` — só colegas cuja troca mantém todas as hard).
2. Escolhe a colega e o turno → pedido criado; **colega e admin notificados** (email + in-app).
3. A colega aceita ou recusa. Ao aceitar: se a organização exigir aprovação do admin, segue para ele; senão aplica-se logo. **Ambas notificadas por email** na aceitação/aplicação; admin sempre informado.
4. Antes de aplicar, o solver revalida a escala completa (estado pode ter mudado desde o pedido). Histórico de trocas visível; `origem=TROCA` no assignment.
Trocas são **entre funcionárias** (AAD↔AAD); o admin não é parte, apenas supervisor.

### F6 — Férias e ausências
Funcionária pede intervalo de férias → solver testa impacto na cobertura (`POST /vacation-impact`) → admin aprova/recusa com essa informação. Ausências (baixa/falta) registadas pelo admin disparam re-otimização parcial do período com aviso. Aprovação de férias notifica a funcionária (email + in-app).

### F7 — Notificações
Laravel Notifications multi-canal: **email** (Brevo/Resend free tier, templates bonitos) + **in-app** (sino com polling de 30s, contagem não lida — ADR-0005). Eventos: convite aceite (→admin), escala publicada (→todas), pedido de troca (→alvo + admin), troca aceite/recusada/aplicada (→ambas + admin), férias pedidas (→admin), férias decididas (→funcionária). Preferências por utilizador (pode desligar email por tipo).

### F8 — Exportação Excel
Export da escala do mês (`maatwebsite/excel`): grelha pessoas×dias com cores por turno + colunas de totais (horas/semana, folgas, fins de semana trabalhados) + folha de resumo por turno/dia. Download direto e/ou guardado no Supabase Storage.

### F9 — Calendário Google/Apple (iCal)
Cada funcionária tem um **feed iCal privado** (`/calendar/{token}.ics`, token revogável): subscrever uma vez no Google/Apple Calendar e os turnos aparecem e **mantêm-se atualizados** (trocas/republicações refletidas). Botões "Adicionar ao Google Calendar" / "Apple Calendar" com instruções. Eventos com nome do turno, horas certas e lembrete configurável. Sem OAuth na v1.

### F10 — Dashboard e auditoria
Dashboard admin: estado do mês, pedidos pendentes, alertas de viabilidade, **equidade** (horas, fins de semana, folgas e saldo de banco de horas por pessoa — gráficos). Dashboard funcionária: próximo turno, semana atual, pedidos. `audit_log` de todas as mutações relevantes (quem, o quê, diff).

## 4. Modelo de dados

O esquema de `docs/01-planeamento.md` §6 é adotado com estas alterações:
- `invitations` ganha `regime`, `contrato`, `fixa_noite` (predefinições do convite) e `revoked_at`.
- `employees` ganha `regime` (`DIA|NOITE|HIBRIDO`) — deriva `elegivel_noite`.
- `users` ganha `calendar_token` (feed iCal) e `notification_prefs_json`.
- `swap_requests` ganha `admin_approval_required` snapshot e timestamps por transição.
- Convenção Laravel: tabelas em inglês (`organizations`, `users`, `invitations`, `employees`, `shift_types`, `coverage_rules`, `rule_configs`, `schedules`, `shift_assignments`, `swap_requests`, `vacation_requests`, `absences`, `notifications`, `audit_logs`).

## 5. Regras

IDs canónicos H1–H10 / S1–S6 em `CONTEXT.md`; interpretações fixadas em ADR-0004; défice estrutural tratado por ADR-0003. O solver é a única implementação (ADR-0002).

## 6. Não-funcionais

- **Mobile-ready:** UI responsive mobile-first; toda a lógica acessível via API Sanctum para a futura app Expo.
- **Idioma:** UI em PT-PT (strings via i18n para futura tradução).
- **Qualidade:** Pest (feature+unit) no Laravel, pytest no solver, CI GitHub Actions (lint+testes nos 2 serviços), Larastan + Pint, ESLint + Prettier.
- **Segurança:** policies por organização em todas as queries, tokens de convite/calendário opacos e revogáveis, rate limiting no login e endpoints públicos.
- **Deploy:** dev local nativo (artisan serve + vite + uvicorn); produção 100% gratuita — Render free (app via Dockerfile + solver) + Supabase free (Postgres+Storage) + cron-job.org (scheduler/fila); domínio próprio (ADR-0005).

## 7. Fases

- **Fase 0 — Fundação do repo:** esqueleto Laravel+Inertia+React, esqueleto solver FastAPI, scripts de dev local, CI. *(sem features)*
- **Fase 1 — Contas e organização:** F1, F2, F3 (sem check de viabilidade avançado).
- **Fase 2 — Solver e geração:** solver H1–H10+S1–S6 incremental, F4 completo, check de viabilidade.
- **Fase 3 — Alterações:** F5 (trocas), F6 (férias/ausências), F7 (notificações — nasce aqui porque trocas dependem delas).
- **Fase 4 — Experiência:** F8 (Excel), F9 (iCal), F10 (dashboards+auditoria), polimento visual, deploy produção.

Cada fase entrega slices verticais utilizáveis; issues no GitHub por slice.

## 8. Fora de âmbito (v1)

Feriados/majorações (Q8), pausas não pagas (Q4), app mobile nativa (preparada, não construída), múltiplas equipas por organização, OAuth Google Calendar (o feed iCal cobre o caso), i18n além de PT.
