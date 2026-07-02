# ADR-0004 — Interpretação da folha manuscrita (resolução Q1–Q8)

**Estado:** aceite · **Data:** 2026-07-02

## Contexto

As regras vêm de uma folha manuscrita do cliente (foto arquivada em `docs/regras-folha.png`). `docs/02-regras-detalhadas.md` listava 8 ambiguidades (Q1–Q8). A análise da foto resolve parte; o resto fica decidido por pressuposto explícito, alterável em `rule_config` sem tocar em código.

## Decisões

| # | Questão | Resolução | Fonte |
|---|---|---|---|
| Q1 | `{ MT` riscado | **Anotação descartada** — está riscada com um X na própria folha. Não se modela nenhuma restrição extra; a matriz de descanso (H3) já proíbe T→M, e M→T no dia seguinte é legal (24h de descanso). | Foto |
| Q2 | Fixas de noite: sempre N ou rotação? | As 2 fixas fazem **só noites**, em ciclo `NNNFF`. Como 2 pessoas não cobrem 2N/dia, o **pool de noite mensal tem ≥4** (fixas + elegíveis). Pressuposto; parametrizável. | Pressuposto |
| Q3 | 37h30 vs 40h | **Contrato individual** por funcionária (a folha lista ambos como opções). | Foto + pressuposto |
| Q4 | Pausa não paga? | Turno = **8h integrais**. 37h30 gerido como média com tolerância do banco de horas (ADR-0003). | Pressuposto |
| Q5 | Cobertura ao fim de semana | **Igual à da semana** por omissão; `coverage_rules` por dia da semana permite reduzir (cenário A do ADR-0003). | Pressuposto |
| Q6 | `MMMFTTT` rígido? | **Orientação (soft, S1)**. O solver desvia-se se necessário para cumprir as hard. | Pressuposto |
| Q7 | Unidade de publicação | **Mês** — a folha diz "Horário Mensal". Ano = 12 meses encadeados. | Foto |
| Q8 | Feriados/domingos especiais | **Fora do âmbito v1.** Não há majoração nem cobertura especial. Registado como possível evolução. | Decisão |

## Requisito adicional extraído da foto

O bloco "Escala" da folha exige que a escala publicada mostre, por funcionária: **nº de horas/semana**, **nº de colaboradoras por turno/dia** e **nº de folgas**. Estes três indicadores entram na UI da grelha e na exportação Excel.

## Consequências

- Nenhuma questão fica a bloquear a modelação; todos os pressupostos estão em `rule_config` ou em flags de perfil, revisitáveis com o cliente sem reescrever o solver.
- Se o cliente esclarecer o `{ MT` no futuro, adiciona-se uma transição proibida à matriz H3 por config.
