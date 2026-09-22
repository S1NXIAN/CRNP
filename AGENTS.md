## Agent skills

### Issue tracker

Issues live in GitHub Issues (`s1nxian/crnp`), managed via the `gh` CLI. See `docs/agents/issue-tracker.md`.

### Triage labels

Default five-role vocabulary (`needs-triage`, `needs-info`, `ready-for-agent`, `ready-for-human`, `wontfix`). See `docs/agents/triage-labels.md`.

### Domain docs

Single-context: root `CONTEXT.md` + `docs/adr/`. See `docs/agents/domain.md`.

### Flowcharts

Spec edits that change a role's flow **update that role's chart before
committing** — same commit, no exceptions; a stale chart is a bug. Charts:
`docs/flow-charts/` → customer · cashier · kitchen · admin. Chart text stays
declarative (what the system does), never build order.
