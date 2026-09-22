# 004 — Reservations are 100% manual retyping

**Status:** fixed
**Severity:** high (lost bookings + violates the plan's own UX rule)
**Personas affected:** admin (top friction), cashier, customer

## Problem

Every reservation is a phone call that staff **retype field by field**:
name, phone, date/time, heads, type — **five inputs**. The plan's own
click-first rule says **≤3 inputs per screen**; the reservation screen
admits it breaks that rule. No phone lookup, no past-customer autocomplete,
no self-serve portal (portal was explicitly rejected).

> Admin: "Every booking is a phone call I retype … that's my lost
> bookings."
> Cashier: "Five inputs the plan itself admits break its own ≤3 rule."

## Plan references

- `docs/PLAN.md` §3 — "≤3 inputs per screen … (reservation: name, phone,
  date/time, heads, type → confirm)" — 5 fields.
- `docs/PLAN.md` §2 — rejected: "Customer self-serve reservation portal …
  a portal would race the staff calendar for no promised requirement."
- `docs/flow-charts/admin.md`, `docs/flow-charts/cashier.md`.

## Fix directions (decide one)

1. **Customer portal revisit** — reopen the rejected portal decision with
   this review as new evidence (ADR-style: rejection rationale vs. scored
   friction). Self-booked = zero staff typing.
2. **Guided 2-step screen** — split the 5 fields into ≤3 + ≤2 across two
   focused steps (still staff-entered, honors the density rule).
3. **Returning-customer lookup** — type last name/phone → prefill name,
   heads, preferred type; staff only adjusts date/time.
4. **Phone-order script + paste** — capture the caller's message once and
   parse into fields (only if parsing is trustworthy; otherwise skip).

## Acceptance criteria

- [x] Staff enter a reservation in **≤3 inputs** for returning customers,
      or bookings arrive without staff at all.
- [x] Conflict/duplicate check stays server-run as today.
- [x] If the portal is reopened, record the decision in `docs/adr/`.

## Resolution (2026-09-22)

Directions 2 + 3 adopted; 1 and 4 declined; the owner added a fifth
direction, folded in here:

- **3 — returning-customer lookup (primary).** Staff type the phone;
  typeahead over past reservations prefills name/party/type
  (`.indexOn phone` on the existing shape — no parallel customer
  table); adjust date/time → confirm = ≤3 inputs.
- **2 — guided two steps (first-timers).** name · phone · type
  (tap-only chips) ≤3 → date/time + party stepper ≤2 → confirm →
  server conflict check.
- **1 — portal declined.** Stays rejected: the new evidence was
  retyping friction, which 2+3 remove at the source; bookings still
  arrive as phone calls, so a portal assumes a behavior change
  nobody promised. Criterion 3 not triggered — **no ADR**.
- **4 — script parsing declined.** Gated on "trustworthy" parsing; a
  silently wrong booking is worse than retyping, and the plan has no
  parsing stack.
- **New: public availability view** (owner's direction) — read-only
  aggregate occupancy on `/`: seats taken vs capacity per slot,
  confirmed bookings only, admin-set capacities (smart defaults),
  server-aggregated, no names, no booking ability. Reverses nothing
  — the portal rejection gets a clarifying note in §2.

- `docs/PLAN.md` — §1 vocabulary + item 7, §2 rejected-list
  clarifier, §3 ≤3-input example, §4 Reservations + Admin settings.
- `docs/flow-charts/cashier.md` (lookup-first entry),
  `docs/flow-charts/customer.md` (occupancy view),
  `docs/flow-charts/admin.md` (capacities in settings).
- `CONTEXT.md` — new **Occupancy view** term.
