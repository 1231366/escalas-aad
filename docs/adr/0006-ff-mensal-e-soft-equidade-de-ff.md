# ADR-0006 — FF mensal é preferência; equidade de FF entre pessoas

**Estado:** aceite · **Data:** 2026-07-03 · **Fonte:** clarificação do dono do projeto · **Altera:** ADR-0004 / CONTEXT.md (classificação H5/H6)

## Contexto

A folha lista duas regras de folgas seguidas: "a cada 7 semanas são obrigadas a ter 2 folgas seguidas" e "1×/mês → 2 folgas seguidas". Tínhamos modelado ambas como hard (H5 e H6). O dono clarificou: **só a das 7 semanas é obrigatória**; a mensal é *preferível*. E acrescentou um requisito de justiça: **todas devem ter um número de FF o mais igual possível**.

A matemática confirma que H6 hard era impossível em períodos curtos: com cobertura 4M/3T/2N (63 turnos/semana) e 12 pessoas, há só 21 folgas/semana → média 1,75 folgas/pessoa → nem toda a gente pode ter um par FF numa semana.

## Decisão

1. **H5 (hard, mantém-se):** em cada janela deslizante de `ff_window_weeks` (7) semanas, cada funcionária tem ≥1 par FF. Aplica-se **apenas a janelas completas** dentro de `initial_state + período` — períodos mais curtos que a janela não ativam a regra (fica garantida pelo encadeamento mensal, issue #14).
2. **H6 → S7 (soft):** ter um par FF em cada mês do calendário passa a preferência com penalização média (peso 5) por pessoa×mês sem FF. A chave `ff_monthly` de `rule_config` mantém-se e liga/desliga a preferência.
3. **S8 (soft, novo):** equidade do número de pares FF entre funcionárias no período — minimizar (máx − mín) com peso alto (8), ao nível de S3/S6.

## Consequências

- Gerar 1 semana volta a ser FEASIBLE; o mês continua a sair com FFs mensais na prática (a preferência empurra), mas sem rebentar quando é apertado.
- IDs canónicos: S7 e S8 juntam-se ao CONTEXT.md; H6 fica documentado como reclassificado (não reutilizar o ID para outra regra).
- UI de regras (admin): o texto de `ff_monthly` deve dizer "preferível", não "obrigatório".
