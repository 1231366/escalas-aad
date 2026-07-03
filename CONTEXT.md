# CONTEXT — Escalas AAD

Glossário e linguagem partilhada do projeto. Usar **sempre** estes termos em código, issues, testes e conversas. Fonte das regras: folha manuscrita do cliente (ver `docs/02-regras-detalhadas.md` e ADR-0004).

## Domínio

- **AAD** — Ajudante de Ação Direta. A funcionária cujo trabalho é escalado. No código: `Employee`.
- **Turno** — bloco de 8h: **M** (Manhã, 08–16), **T** (Tarde, 16–00), **N** (Noite, 00–08). No código: `ShiftType`.
- **Folga (F)** — dia sem turno. Modelada explicitamente como atribuição com `shift_type_id = NULL`, nunca como ausência de registo.
- **Escala** — o horário de um período (mês) para toda a equipa: matriz pessoas × dias. No código: `Schedule`; cada célula é um `ShiftAssignment`. Estados: `DRAFT → PUBLISHED → ARCHIVED`.
- **Cobertura** — nº de AAD exigidas por turno por dia (4M/3T/2N por omissão). Parametrizável por dia da semana em `coverage_rules`.
- **Fixa noite** — funcionária dedicada ao turno N (as "2 fixas" da folha). Flag `fixa_noite` no perfil.
- **Pool de noite / pool de dia** — divisão mensal da equipa imposta pela regra M×N: quem faz M num mês não faz N nesse mês. O pool de noite precisa de ≥4 pessoas (2 fixas + 2 elegíveis) para cobrir 2N/dia com ciclo NNNFF.
- **Elegível noite** — funcionária que *pode* entrar no pool de noite (`elegivel_noite`). Diferente de fixa.
- **Padrão diurno** — sequência de referência `MMMFTTT` (soft, orientação).
- **Padrão de noite** — sequência `NNNFF` (soft, orientação).
- **Regra das 2 folgas** — 2 folgas seguidas ("FF") obrigatórias a cada 7 semanas (janela deslizante completa). Ter FF 1×/mês é **preferência**, não obrigação, e o nº de FF deve ser equitativo entre pessoas (ADR-0006).
- **Matriz de descanso** — transições dia-a-dia permitidas pelas 11h de descanso: proibido N→M, N→T, T→M. Depois de N: só N ou F.
- **Contrato** — carga semanal: `H37_30` (37h30) ou `H40` (40h). Individual por funcionária.
- **Banco de horas** — tolerância configurável sobre a carga contratual que absorve o défice estrutural (ver ADR-0003).
- **Troca (swap)** — pedido de uma funcionária para trocar um turno seu com o de uma colega. Só entre funcionárias; validada pelo solver **antes** de o pedido ser enviado. Fluxo: `PENDING → ACCEPTED/DECLINED → APPLIED` (admin é notificado; aprovação configurável).
- **Candidatas a troca** — lista, calculada pelo solver, de colegas com quem uma troca num dado dia é válida (não parte nenhuma regra hard). É o ecrã "com quem posso trocar".
- **Pedido de férias** — intervalo de dias; o solver testa se a cobertura aguenta sem a pessoa antes de o admin aprovar.
- **Convite** — link com token (partilhável por WhatsApp) que pré-define papel e **regime** da nova funcionária.
- **Regime** — restrição de horário predefinida no convite/perfil: `DIA` (só M/T), `NOITE` (só N), `HIBRIDO` (qualquer).
- **Organização** — tenant. Todas as tabelas de domínio têm `organization_id`.

## Regras (IDs canónicos)

**Hard:** H1 cobertura exata · H2 um turno/pessoa/dia · H3 descanso 11h · H4 M×N incompatíveis no mês · H5 FF a cada 7 semanas (só janelas completas) · H7 carga = contrato ± banco de horas · H8 fixas noite · H9 ≤6 dias consecutivos · H10 ausências respeitadas. *(H6 foi reclassificado para soft — ver S7; não reutilizar o ID.)*
**Soft:** S1 padrão MMMFTTT · S2 padrão NNNFF · S3 equidade fins de semana · S4 preferências · S5 evitar turnos isolados · S6 equidade de carga · S7 FF 1×/mês (preferível, ex-H6) · S8 equidade do nº de FF entre pessoas (ADR-0006).

Referir regras sempre pelo ID (ex.: "isto viola H3"), em código, mensagens de erro e issues.

## Arquitetura (resumo; detalhes nos ADRs)

- **API/Web** — monólito Laravel 11 (MVC) + Inertia + React + TS + Tailwind + shadcn/ui. Auth Sanctum (sessão web + tokens para futura app mobile via `routes/api.php`).
- **Solver** — microserviço Python/FastAPI com OR-Tools CP-SAT. **Única** fonte da lógica de regras; serve geração, validação de trocas, candidatas a troca e teste de férias.
- **BD** — PostgreSQL (Supabase free). **Deploy** — Render free tier (Laravel via Dockerfile + solver Python nativo), domínio do utilizador via DNS. Sem Redis (filas `database`), sem websockets (polling 30s). Custo total: 0€ (ADR-0005).
