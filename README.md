# Sistema de Escalas AAD — Pacote de arranque

Tudo o que precisas para arrancar o sistema de geração de escalas para 12 funcionárias AAD: planeamento, regras, prompt de desenvolvimento e protótipo funcional.

---

## O que está aqui

```
escalas-aad/
├── README.md                          ← este ficheiro
├── CONTEXT.md                         ← glossário do domínio (linguagem partilhada)
├── CLAUDE.md                          ← configuração dos agent skills
├── docs/
│   ├── PRD.md                         ← ★ requisitos do produto (fonte de verdade do plano)
│   ├── regras-folha.png               ← foto da folha manuscrita original com as regras
│   ├── adr/                           ← decisões de arquitetura (stack, solver, viabilidade, Q1–Q8)
│   ├── agents/                        ← config dos skills (issue tracker, labels, domain docs)
│   ├── 01-planeamento.md              ← plano técnico completo (arquitetura, dados, roadmap)
│   ├── 02-regras-detalhadas.md        ← todas as regras explicadas + ambiguidades
│   └── 03-prompt-desenvolvimento.md   ← prompt de arranque original
├── scripts/
│   └── github-setup.sh                ← cria repo GitHub, labels, milestones e issues
└── prototipos/
    └── prototipo-escalas-aad.html     ← protótipo navegável (abre no browser)
```

> **Estado do planeamento:** as questões Q1–Q8 estão resolvidas em `docs/adr/0004-interpretacao-da-folha-q1-q8.md` (com base na foto da folha) e o cenário de viabilidade está decidido em `docs/adr/0003-viabilidade-banco-de-horas-por-omissao.md`. O plano de execução vive nas issues do GitHub (5 milestones, Fase 0→4).

---

## Por onde começar

1. **Lê `docs/02-regras-detalhadas.md`** — é a fonte de verdade das regras. Repara na **secção 6 (viabilidade)** e nas **8 questões por resolver**.
2. **Abre `prototipos/prototipo-escalas-aad.html`** no browser — mostra o produto a funcionar (gerador + validador reais).
3. **Lê `docs/01-planeamento.md`** — a arquitetura completa e o roadmap por fases.
4. **Usa `docs/03-prompt-desenvolvimento.md`** — cola no Claude Code para começar a construir, fase a fase.

---

## O protótipo (o que é real vs mockup)

Abre o HTML e escolhe **Administração** ou **Funcionária**.

**Funciona a sério:**
- **Gerador** de escalas (respeita cobertura 4M/3T/2N, descanso de 11h, máx. dias consecutivos, pool de noite, padrões).
- **Validador** ao vivo — cada regra a verde/âmbar/vermelho sobre o período completo.
- **Trocas** — simula, revalida todas as regras, só aceita se nada partir.
- **Férias** — testa se a cobertura aguenta sem a pessoa.

**Ainda é mockup:** convites, configuração de regras (leitura) e aprovação de pedidos pelo admin.

> **Nota:** o toggle **Horas extra** vem ligado — é o que faz a escala fechar com 12 pessoas. Desliga-o para ver o défice matemático a aparecer (regra R6 a âmbar).

---

## As 3 decisões críticas antes de programar

1. **Viabilidade:** 12 pessoas a 40h dão um défice de 3 turnos/semana. Decide entre cobertura reduzida ao fim de semana, banco de horas, ou +1 funcionária.
2. **Ambiguidades:** resolve as 8 questões (Q1–Q8) em `02-regras-detalhadas.md`, sobretudo o símbolo `{ MT` riscado e se as fixas de noite entram na rotação.
3. **Arquitetura:** a geração faz-se com **OR-Tools CP-SAT** num microserviço Python isolado — o mesmo motor valida trocas e férias. O protótipo usa um algoritmo *greedy* mais simples, que serve para demonstrar mas não garante solução em casos apertados.

---

## Aviso legal

As regras legais (descanso de 11h, máximo de dias consecutivos, banco de horas) são **pressupostos** baseados no Código do Trabalho português e devem ser **confirmadas** com a legislação e a Convenção Coletiva de Trabalho aplicável ao setor antes de produção.
