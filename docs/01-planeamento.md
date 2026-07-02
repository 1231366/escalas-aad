# Sistema de Geração de Escalas — AAD

**Documento de planeamento técnico e funcional**
Versão 0.1 · Rascunho para validação

---

## 1. Sumário executivo

Sistema web multi-tenant para **gerar, validar e gerir escalas** de 12 funcionárias AAD (Ajudante de Ação Direta), garantindo cumprimento automático de todas as regras laborais e operacionais. Fluxo central:

1. **Admin** configura a organização e envia convites por link.
2. **Funcionárias** registam-se e completam o perfil (contrato, elegibilidade a noite, preferências).
3. **Sistema** gera escalas otimizadas para semana / mês / ano.
4. **Funcionárias** pedem **trocas** e **férias**; o sistema **só permite** o que mantém todas as regras cumpridas.
5. **Admin** publica, ajusta e exporta.

### Decisão arquitetural nº 1 (crítica)
A geração de escalas **não é** lógica condicional. É um problema de satisfação de restrições (*Constraint Satisfaction / Optimization*), formalmente um **Nurse Rostering Problem** — NP-hard. A solução é um **solver dedicado (Google OR-Tools CP-SAT)** correndo num microserviço isolado. Tudo o resto (auth, trocas, UI) é CRUD normal à volta desse núcleo.

---

## 2. ⚠️ Análise de viabilidade (ler antes de tudo)

Com os números da folha, a escala **pode ser matematicamente impossível**. Isto tem de ser resolvido no arranque, não na implementação.

| Métrica | Cálculo | Valor |
|---|---|---|
| Turnos preenchidos por dia | 4M + 3T + 2N | **9** |
| Pessoa-horas por dia | 9 × 8h | 72h |
| Turnos por semana | 9 × 7 dias | **63** |
| Turnos disponíveis (12 pax × 5 turnos a 40h) | 12 × 5 | **60** |
| **Défice semanal** | 63 − 60 | **−3 turnos** |
| Carga média real necessária | 63 ÷ 12 × 8h | **42h/semana** |

**Conclusões:**
- Com **12 pessoas a 40h**, faltam **3 turnos/semana**. Impossível cobrir sem horas extra ou banco de horas.
- Com contratos de **37h30**, o défice é ainda maior.
- Cenários de resolução (o sistema deve suportar parametrizar isto):
  - **A)** Reduzir cobertura ao fim de semana (ex.: 3M/2T/2N ao sábado e domingo).
  - **B)** Autorizar horas extra / banco de horas dentro dos limites legais.
  - **C)** Aumentar equipa para ~13–14 pessoas.
  - **D)** Turnos com pausa não paga (ex.: 7h30 efetivas → mais folga na conta, mas mais turnos por pessoa).

> **Ação obrigatória:** validar qual o cenário real antes de modelar as restrições. O solver só encontra solução se ela existir; se der "infeasible", é aqui que está a causa.

---

## 3. Descodificação das regras (o que li da folha)

### 3.1 Turnos
| Código | Turno | Horário | Duração |
|---|---|---|---|
| **M** | Manhã | 08:00 – 16:00 | 8h |
| **T** | Tarde | 16:00 – 00:00 | 8h |
| **N** | Noite | 00:00 – 08:00 | 8h |

### 3.2 Cobertura diária (nº de AAD por turno)
- **M → 4 AAD**
- **T → 3 AAD**
- **N → 2 AAD** (dessas, **2 fixas** dedicadas à noite)

### 3.3 Regras identificadas
1. **Padrão de rotação diurno:** `M M M F T T T` (3 manhãs → folga → 3 tardes).
2. **Padrão de noite:** `N N N F F` (3 noites → 2 folgas).
3. **A cada 7 semanas** → obrigatório **2 folgas seguidas**.
4. **1×/mês** → **2 folgas seguidas**.
5. **Manhã e Noite no mesmo mês são incompatíveis:** quem faz M num mês não pode fazer N nesse mês.
6. **Carga horária semanal:** contratos de **37h30** ou **40h**.
7. **2 funcionárias fixas à noite.**
8. `{ MT` riscado na folha — **significado por confirmar** (ver secção 4).

### 3.4 Restrições legais implícitas (Portugal — a validar com jurídico)
9. **Descanso mínimo de 11h** entre turnos. Isto tem impacto forte:
   - Depois de **N** (acaba 08:00), o turno **M** seguinte (começa 08:00) dá **0h** de descanso → **proibido**.
   - Depois de **N**, um **T** no mesmo dia (começa 16:00) dá 8h → **proibido**.
   - Depois de **N**, só é legal **N** de novo (16h descanso) ou **folga**.
10. **Descanso semanal obrigatório** (mínimo 1 dia; a regra das 2 folgas seguidas reforça isto).
11. **Máximo de dias consecutivos de trabalho** (tipicamente ≤ 6).

---

## 4. Pressupostos e questões críticas a validar

> Estas ambiguidades bloqueiam a modelação correta. Marcar cada uma antes da Fase 2.

| # | Questão | Pressuposto atual | Impacto |
|---|---|---|---|
| Q1 | O que significa `{ MT` riscado? | Interpretado como "não fazer M e T seguidos sem folga", mas incerto | Restrição de sequência |
| Q2 | As "2 fixas noite" fazem **sempre** noite ou entram na rotação `NNNFF`? | Fazem só noite, em ciclo `NNNFF` a pares | Define pool de noite |
| Q3 | 37h30 vs 40h: são contratos por pessoa ou média do ciclo? | Contrato individual por funcionária | Nº de turnos/semana |
| Q4 | Turno de 8h é pago integral ou tem 30 min de pausa? | 8h integrais | Muda a conta das horas |
| Q5 | Cobertura ao fim de semana é igual à da semana? | Igual (pior caso) | Viabilidade (secção 2) |
| Q6 | O padrão `MMMFTTT` é obrigatório à risca ou é orientação? | Orientação (soft), não rígido | Flexibilidade do solver |
| Q7 | "Horário mensal" — a unidade de publicação é o mês? | Sim, publica-se mês a mês | Ciclo de geração |
| Q8 | Feriados/domingos contam diferente? | Ainda não modelado | Legal + custo |

---

## 5. Arquitetura do sistema

```
┌─────────────────────────────────────────────────────────────┐
│                        FRONTEND (SPA)                        │
│         React + TypeScript + Tailwind + Framer Motion         │
│   Portal Admin  │  Portal Funcionária  │  Calendário/Export   │
└───────────────────────────┬─────────────────────────────────┘
                            │ REST / JSON (JWT)
┌───────────────────────────▼─────────────────────────────────┐
│                     API PRINCIPAL (Backend)                   │
│              Node/NestJS  ou  PHP/Laravel                      │
│  Auth · Convites · Trocas · Férias · Validação · Notificações │
└──────────┬──────────────────────────────┬───────────────────┘
           │                              │
           │ chamada síncrona/async       │ ORM
           ▼                              ▼
┌────────────────────────┐      ┌──────────────────────────────┐
│   SOLVER SERVICE        │      │        PostgreSQL             │
│   Python + FastAPI      │      │  Dados + histórico + audit    │
│   Google OR-Tools       │      └──────────────────────────────┘
│   (CP-SAT)              │
│  Gera + valida escalas  │
└────────────────────────┘
```

### 5.1 Porquê esta separação
- **Solver isolado (Python + OR-Tools):** o CP-SAT tem bindings nativos e maduros em Python. Isolá-lo permite escalar/re-tentar sem afetar a API, e reutilizar o **mesmo modelo de restrições** para *gerar* e para *validar trocas*.
- **API principal:** podes usar o teu stack habitual. Recomendo **NestJS (TS)** para partilhar tipos com o frontend, mas **Laravel (PHP)** serve igual — alinha com o que já dominas do SyncRide.
- **PostgreSQL:** multi-tenant por `organization_id`, como já fazes.

### 5.2 O motor de validação é o coração do produto
Trocas e férias **não** são updates simples à base de dados. Cada alteração passa pelo **mesmo conjunto de restrições** do gerador:
> "Esta troca é válida?" = "A escala resultante continua a satisfazer TODAS as restrições hard?"

Isto obriga a que a lógica de regras viva **num único sítio** (o solver service), consumida por três fluxos: geração, validação de troca, validação de férias.

---

## 6. Modelo de dados (PostgreSQL)

```sql
-- Multi-tenancy
organizations        (id, nome, config_json, created_at)

-- Utilizadores e autenticação
users                (id, org_id, email, password_hash, role[ADMIN|EMPLOYEE], status)
invitations          (id, org_id, email, token, role, expires_at, status, accepted_at)

-- Perfil das funcionárias
employees            (id, user_id, org_id, nome,
                      contrato[H37_30|H40],
                      elegivel_noite BOOL,
                      fixa_noite BOOL,          -- as 2 fixas
                      senioridade INT,
                      ativo BOOL)

-- Definições de turnos e regras (parametrizável por org)
shift_types          (id, org_id, codigo[M|T|N], hora_inicio, hora_fim, duracao_h)
coverage_rules       (id, org_id, shift_type_id, dia_semana, min_pax, max_pax)
rule_config          (id, org_id, chave, valor_json)  -- 11h descanso, 7 semanas, etc.

-- Escalas
schedules            (id, org_id, periodo_inicio, periodo_fim,
                      granularidade[SEMANA|MES|ANO],
                      status[DRAFT|PUBLICADA|ARQUIVADA],
                      gerada_em, gerada_por)
shift_assignments    (id, schedule_id, employee_id, data, shift_type_id[nullable=folga],
                      origem[GERADO|TROCA|MANUAL|FERIAS])

-- Alterações
swap_requests        (id, schedule_id, requester_id, target_id,
                      assignment_a_id, assignment_b_id,
                      status[PENDENTE|ACEITE|RECUSADA|APROVADA|APLICADA],
                      validacao_json, created_at)
vacation_requests    (id, employee_id, data_inicio, data_fim,
                      status[PENDENTE|APROVADA|RECUSADA],
                      validacao_json)
absences             (id, employee_id, data_inicio, data_fim, tipo[BAIXA|FALTA|OUTRO])

-- Rasto
notifications        (id, user_id, tipo, payload_json, lida, created_at)
audit_log            (id, org_id, actor_id, acao, entidade, entidade_id, diff_json, ts)
```

**Notas de modelação:**
- **Folga** = `shift_assignments` com `shift_type_id = NULL`. Assim toda a grelha (trabalho + folga) fica explícita e auditável.
- `validacao_json` guarda o resultado da verificação de regras no momento do pedido (para explicar ao utilizador *que* regra bloqueou).
- `rule_config` torna as regras **parametrizáveis** — nunca hardcoded. Se a lei mudar ou o cliente for outro, mexes em dados, não em código.

---

## 7. Modelação das restrições (o núcleo)

Separação essencial entre restrições **hard** (nunca violáveis) e **soft** (otimizar, com penalização).

### 7.1 Restrições HARD
| ID | Restrição | Formalização (resumo) |
|---|---|---|
| H1 | Cobertura exata por turno/dia | Σ pessoas em M = 4, T = 3, N = 2 (por dia) |
| H2 | Um turno por dia por pessoa | Σ turnos(pessoa, dia) ≤ 1 |
| H3 | Descanso 11h | Proibir sequências N→M, N→T, T→M (mesma pessoa) |
| H4 | M e N incompatíveis no mês | Se fez M no mês → 0 turnos N nesse mês |
| H5 | 2 folgas seguidas / 7 semanas | Janela deslizante de 7 semanas contém ≥1 par FF |
| H6 | 2 folgas seguidas / mês | Cada mês contém ≥1 par FF |
| H7 | Carga horária | Σ horas semana = contrato (±tolerância banco de horas) |
| H8 | 2 fixas à noite | 2 employees com `fixa_noite` cobrem sempre N |
| H9 | Máx. dias consecutivos | ≤ 6 dias de trabalho seguidos |
| H10 | Férias/ausências | Pessoa ausente não recebe turnos nesse período |

### 7.2 Restrições SOFT (função objetivo — minimizar penalizações)
| ID | Objetivo | Penalização se violado |
|---|---|---|
| S1 | Seguir padrão `MMMFTTT` | Peso médio |
| S2 | Seguir padrão `NNNFF` | Peso médio |
| S3 | Distribuir fins de semana com equidade | Peso alto |
| S4 | Respeitar preferências pessoais | Peso baixo |
| S5 | Minimizar turnos isolados (1 dia entre folgas) | Peso baixo |
| S6 | Equidade de carga entre pessoas | Peso alto |

### 7.3 Formulação CP-SAT (esqueleto)
```python
from ortools.sat.python import cp_model

model = cp_model.CpModel()

# Variável de decisão: x[e, d, s] = 1 se employee e trabalha turno s no dia d
x = {}
for e in employees:
    for d in days:
        for s in shifts + ['F']:   # F = folga
            x[e, d, s] = model.NewBoolVar(f'x_{e}_{d}_{s}')

# H2: exatamente um estado por pessoa/dia (turno ou folga)
for e in employees:
    for d in days:
        model.AddExactlyOne(x[e, d, s] for s in shifts + ['F'])

# H1: cobertura exata
for d in days:
    model.Add(sum(x[e, d, 'M'] for e in employees) == 4)
    model.Add(sum(x[e, d, 'T'] for e in employees) == 3)
    model.Add(sum(x[e, d, 'N'] for e in employees) == 2)

# H3: descanso 11h — proibir N seguido de M/T no dia seguinte
for e in employees:
    for d in days[:-1]:
        model.Add(x[e, d, 'N'] + x[e, d+1, 'M'] <= 1)
        model.Add(x[e, d, 'N'] + x[e, d+1, 'T'] <= 1)

# ... H4..H10 análogas ...

# Função objetivo: minimizar soma ponderada das violações soft
model.Minimize(sum(peso * violacao for ...))

solver = cp_model.CpSolver()
solver.parameters.max_time_in_seconds = 60   # ano inteiro pode precisar de mais
status = solver.Solve(model)
```

> **Estratégia para o ano inteiro:** resolver o ano de uma vez pode ser pesado. Abordagem prática: **gerar mês a mês** com *continuidade* (passar o estado dos últimos dias do mês anterior como restrição inicial do seguinte). Mantém H5 (7 semanas) verificável numa janela deslizante que atravessa meses.

---

## 8. Fluxos funcionais

### 8.1 Onboarding por convite
```
Admin cria org → define turnos, cobertura e regras
     │
     ├─ Envia convites (email + token único, expira em X dias)
     │
Funcionária → clica no link → regista conta → completa perfil
     │        (contrato, elegível noite?, preferências)
     │
Quando N funcionárias registadas → Admin desbloqueia geração
```

### 8.2 Geração de escala
```
Admin escolhe período (semana/mês/ano) e cenário de cobertura
     │
API → Solver Service (CP-SAT)
     │
     ├─ FEASIBLE  → devolve escala DRAFT → Admin revê → Publica
     │
     └─ INFEASIBLE → devolve as restrições em conflito
                     ("faltam N turnos", "H4 impossível com X pax")
```

### 8.3 Troca entre funcionárias (o fluxo mais delicado)
```
Funcionária A seleciona o seu turno e propõe troca com turno de B
     │
API → Solver valida a escala HIPOTÉTICA (com a troca aplicada)
     │
     ├─ VÁLIDA   → pedido segue para B
     │              │
     │              B aceita → (auto-aprova ou vai a Admin) → aplica
     │
     └─ INVÁLIDA → bloqueia e explica:
                    "Não é possível: violaria o descanso de 11h de B
                     no dia 14 (N seguido de M)."
```

### 8.4 Pedido de férias
```
Funcionária pede período de férias
     │
API → Solver testa: "a cobertura mantém-se sem esta pessoa?"
     │
     ├─ OK        → Admin aprova → re-otimização parcial do período
     │
     └─ EM RISCO  → avisa Admin: "cobertura de N cai abaixo do mínimo
                     nos dias X e Y" → decisão manual
```

---

## 9. Módulos do produto

| Módulo | Descrição | Prioridade |
|---|---|---|
| **1. Auth & Multi-tenancy** | Registo, login, JWT, papéis, isolamento por org | P0 |
| **2. Convites** | Geração de links, expiração, aceitação | P0 |
| **3. Perfis de funcionária** | Contrato, elegibilidade, preferências | P0 |
| **4. Configuração de regras** | Turnos, cobertura, parâmetros legais (parametrizável) | P0 |
| **5. Solver / Geração** | CP-SAT, geração semana/mês/ano | P0 |
| **6. Validação de alterações** | Reutiliza o solver para trocas/férias | P0 |
| **7. Trocas** | Pedido, validação, aceitação, aprovação | P1 |
| **8. Férias & ausências** | Pedidos, impacto na cobertura, baixas | P1 |
| **9. Calendário & visualização** | Vista grelha, por pessoa, por turno | P1 |
| **10. Exportação** | PDF / Excel / iCal | P2 |
| **11. Notificações** | Email + in-app (troca aprovada, escala publicada) | P2 |
| **12. Auditoria & histórico** | Quem mudou o quê e quando | P2 |
| **13. Dashboard de equidade** | Horas, fins de semana e folgas por pessoa | P2 |

---

## 10. Roadmap de implementação

### Fase 0 — Validação (antes de código)
- Resolver as questões Q1–Q8 da secção 4.
- Fechar o cenário de viabilidade (secção 2).
- Confirmar as regras legais com fonte fidedigna (CT / CCT do setor).

### Fase 1 — Fundações (P0)
- Base de dados + multi-tenancy.
- Auth + convites + perfis.
- Configuração de turnos/cobertura/regras.

### Fase 2 — Núcleo do solver (P0)
- Solver service com CP-SAT: restrições H1–H3 primeiro.
- Gerar **1 semana** válida. Depois **1 mês**. Depois **ano** (mês a mês encadeado).
- Adicionar H4–H10 incrementalmente, testando viabilidade a cada passo.

### Fase 3 — Alterações (P1)
- Motor de validação reutilizável.
- Trocas + férias com feedback explicativo.

### Fase 4 — Experiência (P1/P2)
- Calendário, exportação, notificações, auditoria, dashboard de equidade.

---

## 11. Stack recomendado

| Camada | Tecnologia | Justificação |
|---|---|---|
| Frontend | React + TS + Tailwind + Framer Motion | O teu stack habitual |
| API | NestJS (TS) *ou* Laravel (PHP) | TS partilha tipos; PHP alinha com o SyncRide |
| Solver | **Python + FastAPI + Google OR-Tools (CP-SAT)** | Padrão de ouro para rostering, gratuito |
| BD | PostgreSQL | Multi-tenant, já dominas |
| Auth | JWT + refresh tokens | Standard |
| Email | Brevo SMTP | Já usaste no Agency OS |
| Filas (opcional) | Redis + BullMQ | Geração de ano em background |
| Deploy | Docker Compose (3 serviços) | Solver isolado |

---

## 12. Riscos principais

| Risco | Mitigação |
|---|---|
| Escala **infeasible** por falta de pessoas | Resolver secção 2 já na Fase 0 |
| Solver lento no ano inteiro | Geração mensal encadeada + timeout + fila async |
| Regras mal interpretadas | Fechar Q1–Q8 com o cliente antes de modelar |
| Trocas que "parecem" válidas mas violam regra futura | Validar sempre a escala **completa** resultante, não só os 2 dias |
| Alterações legais | Regras em `rule_config` (dados), nunca hardcoded |

---

*Próximo passo sugerido: responder às questões Q1–Q8 e fechar o cenário de viabilidade. Com isso, avanço para o esquema SQL detalhado ou para o protótipo do modelo CP-SAT com dados reais.*
