# 005 — Back-office chores never automate (restock, backup, closures)

**Status:** fixed
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

- `docs/PLAN.md` §4 *Admin — Inventory* — "inventory monitoring (stock movement,
  low-stock flags …)" — decrement only.
- `docs/PLAN.md` §4 *Acceptance* — "weekly export-backup **documented**."
- `docs/PLAN.md` §1 item 5 — "admin hours per weekday + force-close
  toggle" (no scheduling).

## Fix directions (decide some)

1. **Restock path** — add a receiving action (supplier, qty, date) to the
   stock steppers; keep it 1-tap via an "Add stock" row action. Low-stock
   list gets a restock button.
2. **Scheduled backup** — convert the documented weekly export to a
   scheduled cron; owner reads a "last backup" badge instead of running
   anything.
3. **Scheduled closures** — date-range picker for holidays that flips
   force-close automatically; keep the bare toggle for emergencies.
4. **Optional: low-stock reorder note** — system drafts a reorder list
   from sales trend (no supplier API needed for v1).

## Acceptance criteria

- [x] Stock can increase without editing RTDB directly.
- [x] Backups run on a schedule; owner performs **zero** manual exports.
- [x] A holiday closure is configured once, in advance, and self-executes.

## Resolution (2026-09-22)

Directions 1–3 adopted, rescoped by the owner's **stock ruling**;
direction 4 declined.

- **Stock ruling (owner): inventory = rentals only.** Menu products
  lose their stock counters — no stock-checked POST, no decrement on
  insert, no stock to revert on Restore (the double-restore status
  guard stays), no food low-stock flags. Rationale: the counter only
  ever fell, which manufactured false sold-outs and owner
  babysitting — exactly 005's complaint. Sold-out becomes the admin
  one-tap **hide** toggle. The rental stock domain lands with the
  rentals feature (**issue 007, next**).
- **1 — restock path** (rescoped to rental units): **Add stock** row
  action — supplier · qty · date = 3 inputs (qty stepper, date
  defaults today); the low-stock list carries the restock button.
- **2 — scheduled backup**: weekly JSON export on the Laravel
  scheduler (same mechanism as the 002 proof sweep); dashboard gains
  a **last-backup badge** + download; manual fallback stays
  documented.
- **3 — scheduled closures**: date-range picker feeding the existing
  open/closed computation; the bare force-close toggle stays for
  emergencies.
- **4 — declined**: optional even in the issue; suggested reorder
  quantities from sales-trend math mislead worse than a red
  low-stock flag plus dir 1's restock button.

- `docs/PLAN.md` — §1 items 5/14 + checkout, §4 Products, Inventory,
  Open/closed, Data layer, Dashboard, Reports, Settings, Acceptance.
- Charts: customer (drop stock-checked, closure-aware gate), admin
  (hide toggle, restock, scheduled closures, scheduled export +
  badge). Cashier/kitchen untouched.
- `CONTEXT.md` — new **Restock**, **Scheduled closure** terms.
- No ADR — nothing rejected was reversed.
