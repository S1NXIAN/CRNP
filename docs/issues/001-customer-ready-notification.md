# 001 — No customer "order ready" notification

**Status:** open
**Severity:** critical (breaks customer seamlessness + feeds Unclaimed)
**Personas affected:** customer (Seamlessness 4/10), kitchen, cashier

## Problem

The plan's flow says: *"cooked → cashier **Mark served** → customer collects
with the order code"* — but never says **how the customer learns their food
is ready**. No push, no SMS, no screen update tied to "ready". The customer
must stand around, guess, or walk in and ask staff.

This one gap cascades:

- Customer shows up too early or too late → **Unclaimed** ("money kept,
  manual note") — customer calls it "you charge me for food you never tell
  me is done, then keep my money. Brutal."
- Kitchen sees a paid customer standing there with **no ticket on board**
  (never uploaded proof / dismissed) — cook has no idea who's waiting.
- Cashier must manually notice the no-show and type a note during rush.

## Plan references

- `docs/PLAN.md` §1 Customer flow step 5 — "collect" with no alert step.
- `docs/PLAN.md` §1 item 16 — "Paid but never collected → **Unclaimed**
  (money kept)."
- `docs/flow-charts/customer.md`, `docs/flow-charts/kitchen.md`.

## Fix directions (decide one)

1. **In-session flip** — the customer's order screen already flips for
   *Dismissed*; extend it to flip **waiting → READY** the instant the
   cashier taps Mark served (works only if the tab stays open).
2. **SMS/Viber/WhatsApp ready message** — needs a messaging API + cost;
   conflicts with the plan's "Gmail API email — lost its last consumer"
   rejection unless re-justified.
3. **Pickup code board** — a public counter screen showing ready order
   codes (zero per-customer messaging, no new dependency).
4. **Auto-Unclaimed grace** — at minimum, soften the penalty: auto-refund
   or long grace before "money kept", so the missing alert isn't punitive.

## Acceptance criteria

- [ ] A customer learns their order is ready **without asking staff**.
- [ ] Unclaimed rate no longer depends on the customer guessing.
- [ ] Kitchen/cashier can see paid-but-waiting customers the plan hides
      from the board.
