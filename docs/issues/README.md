# Plan review issues — non-techy persona ratings

Generated from four independent non-technical reviews of `docs/PLAN.md`
(customer, cashier, kitchen, admin), scored on **ease of use** and
**seamlessness** with the goal of a **fully system-assisted, low-human-
interaction** system after end-to-end setup.

## Scorecard

| Persona | Ease | Seamlessness |
|---|---|---|
| Customer | 6/10 | 4/10 |
| Cashier | 7/10 | 6/10 |
| Kitchen | 8/10 | 6/10 |
| Admin/Owner | 7/10 | 6/10 |
| **Average** | **7/10** | **5.5/10** |

## Issues — fix one by one

| # | File | Severity | Summary |
|---|---|---|---|
| 001 | [customer-ready-notification](001-customer-ready-notification.md) | critical | Customer never learns order is ready → Unclaimed cascade |
| 002 | [manual-payment-screenshot-race](002-manual-payment-screenshot-race.md) | critical | Manual screenshot upload races the 15-min window |
| 003 | [kitchen-board-depends-on-others-taps](003-kitchen-board-depends-on-others-taps.md) | high | Board truth depends on cashier/customer taps; NEW flash + sleep blind spots |
| 004 | [manual-reservation-entry](004-manual-reservation-entry.md) | high | Reservations retyped 5 fields at a time; violates ≤3-input rule |
| 005 | [back-office-chores-never-automate](005-back-office-chores-never-automate.md) | medium | No restock flow; manual backups; manual holiday closures |
| 006 | [cashier-fraud-watchdog](006-cashier-fraud-watchdog.md) | medium | "Exception-only" review quietly becomes review-everything |

## Workflow

1. Open one issue, read **Fix directions**, pick one.
2. Update `docs/PLAN.md` (+ flow charts / ADR if the fix reverses a
   recorded rejection — e.g. 004's portal).
3. Tick acceptance criteria, flip **Status** to `fixed`.
4. Move to the next.
