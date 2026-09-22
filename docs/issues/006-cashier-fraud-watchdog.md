# 006 — Cashier is the fraud watchdog on every screenshot

**Status:** fixed
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
- `docs/PLAN.md` §4 *Customer site & ordering — Cart → checkout* — "flagged 'unconfirmed' 5 min; cashier taps
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

- [x] The cashier's default state is **not watching** the queue.
- [x] Any residual review is bounded (< N orders/shift) and defined in
      the plan, not implied.
- [x] Reject remains reachable for genuine exceptions after the fact.

## Resolution (2026-09-22)

Combo: **exceptions + spot-check** (dirs 1 + 3; dirs 2/4 split), plus
the owner's ruling on 002's flagged tradeoff.

- **The blanket "unconfirmed" 5-min flag is gone** — the exception is
  now *defined*: at attach the customer enters **amount paid**
  (prefilled = order total) + **GCash ref#** (2 fields, ≤3-input rule
  holds). `amount ≠ total` or blank ref# → **flagged hold**, both
  numbers side by side, one-tap **Approve / Reject**, kitchen waits
  for the tap. Everything else auto-verifies with zero human eyes.
  Expected flag volume: **≤ 2/shift** — criterion 2's N, stated in
  the plan (§4 proof attach + queue).
- **Dir 2 declined (means)**: OCR adds an external dependency to
  derive a number the customer can type, and ref#-match has no
  source to verify against (no GCash API in scope — a "matched" ref
  would be theater). The goal (only mismatches reach the cashier)
  comes from the customer-entered amount vs known total — zero deps.
  Dir 1's "first order from a new account" sub-signal declined too:
  flags every first-time customer — noise, not exception.
- **Dir 3 adopted — recently-verified list**: verified orders sit for
  at-leisure spot-check, no quota, no timer (criterion 1: default
  state is not watching). **Reject stays reachable until Mark
  served** (criterion 3); after settlement the owner reviews via the
  sales report + rejection record (dir 4's after-the-fact half).
- **002 tradeoff revisited (owner ruling)**: rejected proofs are no
  longer deleted immediately — kept **7 days after Reject** as
  watchdog evidence, and Reject writes a **rejection record** (order
  id, entered vs total, ref#, cashier, timestamp). Reverses 002's
  addendum line; plan edit only, not a §2 recorded rejection (no
  ADR).

- `docs/PLAN.md` — §1 payment gate + Pay/prove item 15, §3
  default-yes ×2, §4 proof attach, online pickup queue, proof
  lifecycle, role table, acceptance happy path.
- Charts: cashier (flag → Approve/Reject decision, recently-verified
  list, 7-day rejection evidence), customer (amount + ref at attach).
- `CONTEXT.md` — **Payment verified** amended; new **Flagged hold**,
  **Recently-verified list** terms.
- No ADR.
