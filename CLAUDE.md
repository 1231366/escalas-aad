# Escalas AAD

Sistema web para gerar e gerir escalas de turnos de equipas AAD (Ajudantes de Ação Direta): geração automática por constraint solver, trocas entre funcionárias validadas antes do pedido, férias, convites por link, exportação Excel, integração com Google/Apple Calendar.

Ver `README.md` e `docs/01-planeamento.md`, `docs/02-regras-detalhadas.md` para o domínio e as regras completas.

## Agent skills

### Issue tracker

GitHub Issues, com PRs externos também como superfície de triagem. Ver `docs/agents/issue-tracker.md`.

### Triage labels

Nomes por omissão (`needs-triage`, `needs-info`, `ready-for-agent`, `ready-for-human`, `wontfix`). Ver `docs/agents/triage-labels.md`.

### Domain docs

Single-context — um `CONTEXT.md` + `docs/adr/` na raiz, partilhado entre o monolito Laravel e o microserviço solver Python. Ver `docs/agents/domain.md`.
