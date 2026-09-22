# 005 — Back-office chores never automate (restock, backup, closures)

**Status:** open
**Severity:** medium (owner babysitting after "full setup")
**Personas affected:** admin/owner (Seamlessness 6/10)

## Problem

The plan is ~80% automated on the happy path, but three back-office tasks
stay manual **forever**, contradicting "set it up once, the system runs
itself":

1. **No restock flow** — inventory has `− / +` steppers, stock movement,
   low-stock flags … but **nothing ever goes up** except the owner's hands.
   The owner *is* the stock ledger.
2. **Weekly export-backup is "documented"** = the owner runs it, weekly,
   by memory.
3. **Force-close is a bare toggle** — every holiday/temporary closure is
   a manual flip; no date-range scheduler.

> Admin: "Automate reservations, restock, and backups before you call it
> 'runs itself.'"

## Plan references

- `docs/PLAN.md` §5 *Admin — Inventory* — "inventory monitoring (stock movement,
  low-stock flags …)" — decrement only.
- `docs/PLAN.md` §5 *Acceptance* — "weekly export-backup **documented**."
- `docs/PLAN.md` §1 item 5 — "admin hours per weekday + force-close
  toggle" (no scheduling).

## Fix directions (decide some)

1. **Restock path** — add a receiving action (supplier, qty, date) to the
   stock steppers; keep it 1-tap via an "Add stock" row action. Low-stock
   list gets a restock button.
2. **Scheduled backup** — convert the documented weekly export to the
   in-container cron already used for keepalive (`render.yaml` / §4);
   owner reads a "last backup" badge instead of running anything.
3. **Scheduled closures** — date-range picker for holidays that flips
   force-close automatically; keep the bare toggle for emergencies.
4. **Optional: low-stock reorder note** — system drafts a reorder list
   from sales trend (no supplier API needed for v1).

## Acceptance criteria

- [ ] Stock can increase without editing RTDB directly.
- [ ] Backups run on a schedule; owner performs **zero** manual exports.
- [ ] A holiday closure is configured once, in advance, and self-executes.
