# ADR-0002 — Solver CP-SAT como fonte única da lógica de regras

**Estado:** aceite · **Data:** 2026-07-02

## Contexto

Gerar escalas com estas regras é um **Nurse Rostering Problem** (NP-hard) — não se resolve com lógica condicional. Além da geração, o sistema valida trocas, calcula candidatas a troca e testa pedidos de férias. Se a lógica de regras existir em dois sítios (gerador + validadores ad-hoc no Laravel), divergem inevitavelmente.

## Decisão

Toda a lógica de regras (H1–H10, S1–S6) vive **exclusivamente** no serviço solver (Python + OR-Tools CP-SAT). O Laravel nunca reimplementa regras; consome quatro endpoints:

- `POST /generate` — gera escala para um período (mês; ano = meses encadeados passando o estado final de um como condição inicial do seguinte). Devolve escala ou, se *infeasible*, as restrições em conflito com IDs (ex.: "H1 impossível no dia 14: faltam 2 turnos N").
- `POST /validate` — recebe uma escala hipotética completa e devolve as violações (ID da regra + dia + pessoa). Usado para aplicar trocas e edições manuais do admin.
- `POST /swap-candidates` — dado um assignment de uma funcionária, devolve as colegas com quem a troca é válida nesse dia (pré-cálculo do ecrã "com quem posso trocar").
- `POST /vacation-impact` — dado um intervalo de férias, devolve se a cobertura aguenta e onde fica em risco.

Regras parametrizadas por request (o Laravel envia a config da organização de `rule_config`/`coverage_rules`); o solver é stateless e não acede à BD.

## Consequências

- Uma troca é válida ⇔ a escala completa resultante continua a satisfazer todas as hard — nunca só os 2 dias trocados.
- Solver stateless → fácil de escalar/reiniciar; testável com pytest puro sobre casos de regras.
- Chamadas de geração correm em Job do Laravel (fila Redis) com timeout; validações pontuais (troca única) são síncronas.
- Mensagens de violação incluem sempre o **ID canónico** da regra (CONTEXT.md) para a UI explicar "porquê".
