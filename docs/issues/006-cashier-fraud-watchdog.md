# 006 — Cashier is the fraud watchdog on every screenshot

**Status:** open
**Severity:** medium (turns "default-yes" into per-order manual review)
**Personas affected:** cashier (Seamlessness 6/10 driver)

## Problem

The plan's selling point is **default-yes**: proof auto-verifies, the
cashier taps **Reject only on exception**. But the spec also says proof is
*"flagged 'unconfirmed' 5 min; cashier taps Reject only on exception."*

Combined, that means the cashier must **eyeball every uploaded screenshot
for at least 5 minutes** — the system auto-accepts while the human carries
the fraud risk. During rush hour, "exception-only" quietly becomes
"review-everything," which is exactly the manual load the plan promises to
remove.

> Cashier: "The system auto-accepts; I carry the fraud risk. That's my
> manual checking, every order."

## Plan references

- `docs/PLAN.md` §1 — "the customer's uploaded GCash screenshot
  **auto-verifies**; the cashier taps **Reject** only on exception."
- `docs/PLAN.md` §5 item 4 — "flagged 'unconfirmed' 5 min; cashier taps
  Reject only on exception."
- `docs/PLAN.md` §3 — "Default-yes, exception-only taps."

## Fix directions (decide one)

1. **Define the exception** — decide what actually warrants the 5-min
   flag (e.g., amount mismatch, missing ref#, first order from a new
   account) so the cashier reviews **a few**, not all. If nothing
   qualifies, delete the flag entirely.
2. **Amount auto-match** — OCR or GCash ref-number match against the
   order total; only mismatches reach the cashier.
3. **Sampling instead of 100%** — auto-verify fully; surface a
   "recently verified" list the cashier can spot-check at leisure, with
   Reject still available afterward.
4. **Drop the 5-min flag** — trust auto-verify completely; treat fraud as
   an after-the-fact review via the sales report (accept the demo-scale
   risk).

## Acceptance criteria

- [ ] The cashier's default state is **not watching** the queue.
- [ ] Any residual review is bounded (< N orders/shift) and defined in
      the plan, not implied.
- [ ] Reject remains reachable for genuine exceptions after the fact.
