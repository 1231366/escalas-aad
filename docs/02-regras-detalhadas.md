# Regras da Escala AAD — Explicação detalhada

Documento de referência com **todas as regras** extraídas da folha manuscrita, a sua interpretação, formalização e ambiguidades por resolver. É a fonte de verdade para o gerador e o validador.

---

## 1. Turnos

Três turnos de **8 horas** cada, a cobrir as 24h do dia.

| Código | Turno | Horário | Duração |
|--------|-------|---------|---------|
| **M** | Manhã | 08:00 – 16:00 | 8h |
| **T** | Tarde | 16:00 – 00:00 | 8h |
| **N** | Noite | 00:00 – 08:00 | 8h |
| **F** | Folga | — | — |

---

## 2. Cobertura diária (quantas AAD por turno)

Todos os dias têm de ter exatamente:

| Turno | AAD necessárias |
|-------|-----------------|
| **Manhã** | 4 |
| **Tarde** | 3 |
| **Noite** | 2 (das quais **2 fixas**) |

**Total: 9 turnos preenchidos/dia**, das 12 funcionárias. As restantes 3 ficam de folga.

---

## 3. Regras operacionais (da folha)

### R1 · Padrão de rotação diurno
Sequência de referência: **`M M M F T T T`** (3 manhãs → folga → 3 tardes).
> **Interpretação:** orientação de rotação, não obrigação rígida. Ajuda a criar blocos coerentes, mas o gerador pode desviar-se se necessário para cumprir cobertura. **A validar (Q6):** é rígido ou orientação?

### R2 · Padrão de noite
Sequência: **`N N N F F`** (3 noites → 2 folgas).
> Ciclo clássico de 5 dias para quem faz noite.

### R3 · Duas folgas seguidas — periodicidade
- **A cada 7 semanas** → obrigatório ter **2 folgas seguidas**.
- **1×/mês** → **2 folgas seguidas**.
> **Interpretação:** garante descanso prolongado regular. No validador, verifica-se por janela deslizante.

### R4 · Manhã × Noite incompatíveis
Quem faz **Manhã** num mês **não pode fazer Noite** no mesmo mês (e vice-versa).
> **Consequência de design:** divide a equipa em dois *pools* por mês — **pool de dia** (M/T) e **pool de noite** (N). No protótipo, 4 pessoas ficam no pool de noite e 8 no pool de dia.

### R5 · Duas fixas à noite
**2 funcionárias fixas** dedicadas ao turno da noite.
> **A validar (Q2):** as fixas fazem *sempre* noite ou entram na rotação `NNNFF`? Se seguirem `NNNFF`, 2 pessoas não chegam para cobrir 2/noite todos os dias (nos dias FF ninguém cobre) — daí o pool de noite ter de ser maior (≥4).

### R6 · Carga horária
Dois tipos de contrato: **37h30** ou **40h** por semana.
> 40h = 5 turnos de 8h/semana. 37h30 **não** é múltiplo de 8h — **a validar (Q3/Q4):** é média do ciclo? Há pausa não paga (7h30 efetivas)? São contratos individuais?

### R7 · Símbolo `{ MT` riscado
Aparece riscado na folha, junto ao padrão diurno.
> **Significado desconhecido.** Hipótese: proibir a sequência M seguido de T sem folga. **A confirmar (Q1).**

---

## 4. Regras legais implícitas (Portugal — confirmar com CT / CCT do setor)

### L1 · Descanso mínimo de 11h entre turnos
Impacto direto nas transições possíveis de um dia para o outro:

| Ontem \ Hoje | M (08h) | T (16h) | N (00h) | F |
|--------------|:-------:|:-------:|:-------:|:-:|
| **M** (fim 16h) | ✓ 16h | ✓ 24h | ✗ 8h | ✓ |
| **T** (fim 00h) | ✗ 8h | ✓ 16h | ✗ 0h | ✓ |
| **N** (fim 08h) | ✗ 0h | ✗ 8h | ✓ 16h | ✓ |
| **F** | ✓ | ✓ | ✓ | ✓ |

> **Leitura:** depois de uma **Noite**, só é legal outra **Noite** ou **Folga**. Depois de uma **Tarde**, não se pode fazer **Manhã** no dia seguinte.

### L2 · Máximo de dias consecutivos de trabalho
Tipicamente **≤ 6 dias** seguidos.

### L3 · Descanso semanal
Mínimo 1 dia/semana (reforçado pela R3).

---

## 5. Classificação: Hard vs Soft

O gerador trata as regras em dois níveis.

### Restrições HARD (nunca violáveis)
Cobertura exata (secção 2) · Um turno por dia · Descanso 11h (L1) · M×N no mês (R4) · 2 folgas seguidas (R3) · Carga horária (R6) · 2 fixas noite (R5) · Máx. dias consecutivos (L2) · Ausências/férias respeitadas.

### Restrições SOFT (otimizar, com penalização)
Seguir padrão `MMMFTTT` (R1) · Seguir `NNNFF` (R2) · Equidade de fins de semana · Preferências pessoais · Minimizar turnos isolados · Equidade de carga entre pessoas.

---

## 6. ⚠️ Alerta de viabilidade

| Métrica | Cálculo | Valor |
|---------|---------|-------|
| Turnos/dia | 4M+3T+2N | 9 |
| Turnos/semana | 9×7 | **63** |
| Disponível (12 pax × 5 turnos a 40h) | 12×5 | **60** |
| **Défice** | 63−60 | **−3/semana** |
| Carga média necessária | 63÷12×8h | **42h/semana** |

Com **12 pessoas a 40h**, a escala **não fecha sem horas extra**. Opções: reduzir cobertura ao fim de semana · autorizar banco de horas · +1 funcionária. Esta decisão tem de ser tomada antes da modelação final.

---

## 7. Questões por resolver (bloqueiam a modelação final)

| # | Questão | Pressuposto atual |
|---|---------|-------------------|
| Q1 | Significado do `{ MT` riscado | Proibir M→T sem folga (incerto) |
| Q2 | Fixas de noite: sempre N ou rotação `NNNFF`? | Pool de noite dedicado (4 pax) |
| Q3 | 37h30 vs 40h: contrato individual ou média? | Individual |
| Q4 | Turno de 8h é pago integral ou tem pausa? | Integral |
| Q5 | Cobertura ao fim de semana = à da semana? | Igual |
| Q6 | `MMMFTTT` é rígido ou orientação? | Orientação (soft) |
| Q7 | Unidade de publicação é o mês? | Sim |
| Q8 | Feriados/domingos contam diferente? | Ainda não modelado |
