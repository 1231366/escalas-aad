# Prompt de desenvolvimento — Sistema de Escalas AAD

Copia este briefing para o **Claude Code** (ou outro agente) para arrancar o desenvolvimento. Está estruturado por fases; podes pedir uma fase de cada vez.

---

## Contexto

Preciso de construir um **sistema web multi-tenant de geração e gestão de escalas** para equipas de AAD (Ajudantes de Ação Direta) num contexto de apoio/cuidados. É formalmente um **Nurse Rostering Problem** (NP-hard): a geração automática faz-se com um **constraint solver**, não com lógica condicional.

**Fluxo central:** admin configura a organização e envia convites por link → funcionárias registam-se e completam o perfil → sistema gera escalas (semana/mês/ano) cumprindo todas as regras → funcionárias pedem trocas e férias, que o sistema só permite se mantiverem todas as regras → admin publica e exporta.

---

## Stack decidido

| Camada | Tecnologia |
|--------|-----------|
| Frontend | React + TypeScript + Tailwind + Framer Motion |
| API | NestJS (TS) *ou* Laravel (PHP) |
| **Solver** | **Python + FastAPI + Google OR-Tools (CP-SAT)** — microserviço isolado |
| Base de dados | PostgreSQL (multi-tenant por `organization_id`) |
| Auth | JWT + refresh tokens |
| Email | Brevo SMTP |
| Deploy | Docker Compose (3 serviços: frontend, api, solver) |

**Princípio-chave:** a lógica das regras vive **num único sítio** — o solver service. É consumida por três fluxos: geração, validação de trocas, validação de férias.

---

## Modelo de dados (PostgreSQL)

```sql
organizations     (id, nome, config_json, created_at)
users             (id, org_id, email, password_hash, role[ADMIN|EMPLOYEE], status)
invitations       (id, org_id, email, token, role, expires_at, status, accepted_at)
employees         (id, user_id, org_id, nome, contrato[H37_30|H40],
                   elegivel_noite BOOL, fixa_noite BOOL, senioridade INT, ativo BOOL)
shift_types       (id, org_id, codigo[M|T|N], hora_inicio, hora_fim, duracao_h)
coverage_rules    (id, org_id, shift_type_id, dia_semana, min_pax, max_pax)
rule_config       (id, org_id, chave, valor_json)
schedules         (id, org_id, periodo_inicio, periodo_fim,
                   granularidade[SEMANA|MES|ANO], status[DRAFT|PUBLICADA|ARQUIVADA],
                   gerada_em, gerada_por)
shift_assignments (id, schedule_id, employee_id, data, shift_type_id NULL,
                   origem[GERADO|TROCA|MANUAL|FERIAS])
swap_requests     (id, schedule_id, requester_id, target_id,
                   assignment_a_id, assignment_b_id,
                   status[PENDENTE|ACEITE|RECUSADA|APROVADA|APLICADA], validacao_json)
vacation_requests (id, employee_id, data_inicio, data_fim,
                   status[PENDENTE|APROVADA|RECUSADA], validacao_json)
absences          (id, employee_id, data_inicio, data_fim, tipo[BAIXA|FALTA|OUTRO])
notifications     (id, user_id, tipo, payload_json, lida, created_at)
audit_log         (id, org_id, actor_id, acao, entidade, entidade_id, diff_json, ts)
```

**Notas:** folga = `shift_assignments` com `shift_type_id = NULL`. As regras ficam **parametrizáveis** em `rule_config` — nunca hardcoded.

---

## Regras a modelar no solver

### HARD (nunca violáveis)
| ID | Regra |
|----|-------|
| H1 | Cobertura exata: 4 Manhã, 3 Tarde, 2 Noite por dia |
| H2 | Um turno por pessoa por dia |
| H3 | Descanso 11h: proibir N→M, N→T, T→M (mesma pessoa, dias consecutivos) |
| H4 | Manhã e Noite incompatíveis no mesmo mês (por pessoa) |
| H5 | 2 folgas seguidas a cada 7 semanas |
| H6 | 2 folgas seguidas 1×/mês |
| H7 | Carga horária = contrato (37h30 ou 40h), com tolerância de banco de horas |
| H8 | 2 fixas à noite |
| H9 | Máximo 6 dias de trabalho consecutivos |
| H10 | Férias/ausências respeitadas |

### SOFT (otimizar via função objetivo)
| ID | Objetivo | Peso |
|----|----------|------|
| S1 | Seguir padrão MMMFTTT | médio |
| S2 | Seguir padrão NNNFF | médio |
| S3 | Equidade de fins de semana | alto |
| S4 | Preferências pessoais | baixo |
| S5 | Minimizar turnos isolados | baixo |
| S6 | Equidade de carga entre pessoas | alto |

### Matriz de descanso (ontem → hoje)
```
        M    T    N    F
M       ✓    ✓    ✗    ✓
T       ✗    ✓    ✗    ✓
N       ✗    ✗    ✓    ✓
F       ✓    ✓    ✓    ✓
```

---

## ⚠️ Restrição de viabilidade

Com 12 pessoas: 9 turnos/dia × 7 = 63 turnos/semana, mas 12 × 5 turnos (40h) = 60 disponíveis → **défice de 3 turnos/semana**. O solver tem de suportar um modo com **horas extra / banco de horas** ou **cobertura reduzida ao fim de semana**, senão devolve *infeasible*. Torna isto configurável.

---

## Plano por fases (pede uma de cada vez)

### Fase 1 — Fundações
- Esquema PostgreSQL + migrations.
- Multi-tenancy por `organization_id`.
- Auth (registo, login, JWT, papéis ADMIN/EMPLOYEE).
- Convites por link com token e expiração.
- CRUD de funcionárias e perfis (contrato, elegibilidade a noite).
- Configuração de turnos, cobertura e `rule_config`.

### Fase 2 — Solver (núcleo)
- Microserviço Python + FastAPI + OR-Tools CP-SAT.
- Modelar variáveis `x[e,d,s]` (employee × dia × turno).
- Implementar H1–H3 primeiro; gerar **1 semana** válida.
- Adicionar H4–H10 incrementalmente, testando viabilidade a cada passo.
- Função objetivo com as soft (S1–S6).
- Geração de **mês** e de **ano** (mês a mês encadeado, passando o estado dos últimos dias como condição inicial).
- Endpoint `POST /generate` → devolve escala ou lista de restrições em conflito se *infeasible*.

### Fase 3 — Alterações
- Endpoint `POST /validate` reutilizável: recebe uma escala hipotética e devolve as regras violadas.
- Trocas: aplicar troca numa cópia → validar → se OK, notificar colega → aprovação → aplicar.
- Férias: testar se a cobertura aguenta sem a pessoa → aviso ao admin → re-otimização parcial do período.
- Feedback explicativo: dizer **que** regra bloqueou e em que dia.

### Fase 4 — Experiência
- Frontend: portal admin (painel, funcionárias, regras, escalas, pedidos) + portal funcionária (o meu horário, pedir troca, pedir férias).
- Calendário/grelha (matriz pessoas × dias, cores por turno).
- Exportação PDF/Excel/iCal.
- Notificações (email + in-app).
- Auditoria e dashboard de equidade (horas, fins de semana, folgas por pessoa).

---

## Esqueleto do solver (ponto de partida)

```python
from ortools.sat.python import cp_model

def gerar_escala(employees, days, coverage, rest_ok, config):
    model = cp_model.CpModel()
    shifts = ['M', 'T', 'N', 'F']

    # x[e,d,s] = 1 se employee e faz turno s no dia d
    x = {}
    for e in employees:
        for d in range(days):
            for s in shifts:
                x[e, d, s] = model.NewBoolVar(f'x_{e}_{d}_{s}')

    # H2: exatamente um estado por pessoa/dia
    for e in employees:
        for d in range(days):
            model.AddExactlyOne(x[e, d, s] for s in shifts)

    # H1: cobertura exata
    for d in range(days):
        model.Add(sum(x[e, d, 'M'] for e in employees) == coverage['M'])
        model.Add(sum(x[e, d, 'T'] for e in employees) == coverage['T'])
        model.Add(sum(x[e, d, 'N'] for e in employees) == coverage['N'])

    # H3: descanso 11h (transições proibidas)
    proibidas = [('N','M'), ('N','T'), ('T','M')]
    for e in employees:
        for d in range(days - 1):
            for (ontem, hoje) in proibidas:
                model.Add(x[e, d, ontem] + x[e, d+1, hoje] <= 1)

    # H9: máximo 6 dias consecutivos de trabalho
    for e in employees:
        for d in range(days - 6):
            model.Add(sum(x[e, d+i, 'F'] for i in range(7)) >= 1)

    # ... H4, H5, H6, H7, H8, H10 ...
    # ... função objetivo com soft constraints ...

    solver = cp_model.CpSolver()
    solver.parameters.max_time_in_seconds = 60
    status = solver.Solve(model)
    return status, x, solver
```

---

## Como começar

Diz ao agente, por exemplo:

> "Começa pela **Fase 1**: gera o esquema PostgreSQL completo com migrations e o módulo de auth com convites por link. Stack NestJS + Prisma. Multi-tenant por organization_id."

Depois, fase a fase, até ao solver e ao frontend.
