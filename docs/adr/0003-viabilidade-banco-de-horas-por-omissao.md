# ADR-0003 — Défice estrutural: banco de horas por omissão, cobertura parametrizável

**Estado:** aceite · **Data:** 2026-07-02

## Contexto

A cobertura 4M/3T/2N exige 63 turnos/semana; 12 pessoas a 40h dão 60 → **défice de −3 turnos/semana** (docs/01-planeamento.md §2). Com contratos de 37h30 o défice é maior. Sem resolver isto, o solver devolve *infeasible* sempre. Cenários possíveis: (A) reduzir cobertura ao fim de semana, (B) banco de horas/horas extra, (C) +1 funcionária, (D) turnos com pausa não paga.

## Decisão

1. **Por omissão, cenário B:** a regra H7 aceita `carga semanal ≤ contrato + tolerância` com tolerância configurável (`rule_config.hour_bank_weekly_tolerance`, default **+4h/semana**, suficiente para absorver 42h de carga média real vs contrato de 40h). O saldo de banco de horas por pessoa é visível no dashboard de equidade.
2. **Cenário A sempre disponível:** `coverage_rules` é por dia da semana, logo o admin pode baixar a cobertura de sábado/domingo (ex.: 3M/2T/2N) sem código novo.
3. Cenários C e D não requerem nada: mais uma funcionária é só mais um registo; pausas não pagas ficam fora do modelo v1 (turno = 8h integrais, pressuposto Q4).
4. No onboarding da organização, a UI mostra o **check de viabilidade** (oferta vs procura de turnos/semana) antes de permitir gerar, com aviso e sugestões quando o défice existe.

## Consequências

- O solver nunca é chamado num cenário matematicamente impossível sem o admin ter sido avisado.
- A decisão real (que o cliente ainda não tomou) fica em **dados**, não em código — mudar de cenário é editar config.
- As regras legais (11h, 6 dias consecutivos, banco de horas) permanecem pressupostos a confirmar com o CT/CCT antes de produção — aviso mantido no README.
