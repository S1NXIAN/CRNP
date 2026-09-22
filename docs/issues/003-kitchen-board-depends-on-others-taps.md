# 003 — Kitchen board only as honest as other people's thumbs

**Status:** fixed
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
- `docs/PLAN.md` §4 *Kitchen board — Hygiene* — NEW flash ~5 s, 5–10 s fetch, heartbeat
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

- [x] A stale ticket never sits in NOW indefinitely just because the
      cashier is busy.
- [x] Paid-but-unverified customers are at least *visible* somewhere on
      the board or the counter screen.
- [x] A cook who blinks during the NEW flash never misses a ticket.
- [x] A slept/restarted instance recovers on the board without human
      intervention.

## Resolution (2026-09-22)

All four directions decided:

- **1 — auto-clear, adopted with a neutral destination.** The server
  clears any NOW ticket at **ready-for + 15 min (or close)** with zero
  taps → counter **ready — awaiting handover** row; **Unclaimed** is
  decided at the **close sweep**, never by the clock. `ready-for` now
  defined for every origin: scheduled = pickup time, ASAP online =
  verify + 15, walk-in = POS entry + 15 (cook lead, hardcoded v1).
- **2 — declined.** Already solved by 001's **awaiting-proof counter
  row** (the criterion allows board *or* counter). The board keeps the
  verified-unserved-only invariant; and "paid but not attached" is
  unknowable server-side without a GCash transaction API (002
  direction 3, declined).
- **3 — adopted.** Flash ~5 s, then a **steady NEW badge until age
  3 min** — stateless, survives reloads; the age timer takes over
  after.
- **4 — adopted.** **Fixed 5 s fetch**; past ~15 s stale the board
  shows **"Reconnecting…"** and **auto-reloads** with backoff — a
  slept kiosk instance recovers with no cook action.

- `docs/PLAN.md` — §2 stack row, §1 kitchen/Unclaimed/item 16, §4 NOW
  lane + ready-for definition + Hygiene.
- `docs/flow-charts/kitchen.md` (badge, reconnect, clear node),
  `docs/flow-charts/cashier.md` (ready-awaiting-handover row; Unclaimed
  moves to the close sweep). Customer chart untouched.
- `CONTEXT.md` — new **Ready-for** term; **Unclaimed** records the
  close-sweep rule.
- No ADR — direction 1's own caveat answered: server-owned clearing is
  not a cook status, zero cook input preserved.
