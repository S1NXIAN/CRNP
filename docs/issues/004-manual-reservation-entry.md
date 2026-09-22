# 004 — Reservations are 100% manual retyping

**Status:** open
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

- [ ] Staff enter a reservation in **≤3 inputs** for returning customers,
      or bookings arrive without staff at all.
- [ ] Conflict/duplicate check stays server-run as today.
- [ ] If the portal is reopened, record the decision in `docs/adr/`.
