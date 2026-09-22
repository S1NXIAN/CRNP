# 003 — Kitchen board only as honest as other people's thumbs

**Status:** open
**Severity:** high (cook trust in the display)
**Personas affected:** kitchen (Seamlessness 6/10), cashier

## Problem

The kitchen screen is advertised as "zero interaction," but its **truth
depends on two other humans**:

1. Tickets only **appear** after the customer uploads proof that
   auto-verifies — a paying customer who never uploads (or misses the
   window) is invisible to the cook while standing at the counter.
2. Tickets only **clear** when the cashier taps **Mark served** — if the
   cashier walks away mid-rush, cooked tickets pile up in NOW and the cook
   must guess what's live or yell at the counter.

Plus two display-timing gripes:

- **NEW flash dies in ~5 s** with no persistent marker; a cook looking at
  the pan misses it entirely.
- **5–10 s poll + sleeping Render instance** = the cook stares at a dead
  screen with no way to restart it (heartbeat greys at 15 s but that's
  all it says).

> Cook: "My board is only as honest as the cashier's thumbs and the
> customer's uploads."

## Plan references

- `docs/PLAN.md` §1 — "kitchen ticket appears the same instant [as
  auto-verify]"; "tickets clear on **Mark served**."
- `docs/PLAN.md` §5 *Kitchen board — Hygiene* — NEW flash ~5 s, 5–10 s fetch, heartbeat
  grey-out at ~15 s.
- `docs/flow-charts/kitchen.md`.

## Fix directions (decide some)

1. **Auto-clear option** — a ticket auto-expires from NOW at ready-for +
   grace, or clears on a cook-glanceable timer, so the cashier's tap isn't
   the only exit. (Careful: plan rejected cook statuses — this is still
   zero cook input, just server-owned clearing.)
2. **Dismissed visibility** — surface a dim "paid/expected but not
   verified" row so the cook knows someone is waiting.
3. **Persistent NEW marker** — keep the flash for ~5 s but leave a badge
   until the ticket first appears in view or ages past a threshold.
4. **Poll + sleep hardening** — shorten poll, and have the board show a
   plain "reconnecting…" affordance (auto-reload) instead of a silent grey.

## Acceptance criteria

- [ ] A stale ticket never sits in NOW indefinitely just because the
      cashier is busy.
- [ ] Paid-but-unverified customers are at least *visible* somewhere on
      the board or the counter screen.
- [ ] A cook who blinks during the NEW flash never misses a ticket.
- [ ] A slept/restarted instance recovers on the board without human
      intervention.
