# 007 — Rentals not implemented: no catalog, no booking, no return

**Status:** fixed
**Severity:** high (the business is named after it; 005's stock ruling made it load-bearing)
**Personas affected:** customer (Seamlessness), admin/owner (inventory is a dead end)

## Problem

The owner's stock ruling (issue 005) made **inventory = rental units
only**, and the plan now says stock "falls via rental handovers" —
but **nothing rents anything**. No catalog, no booking, no handover,
no return:

1. **No catalog** — customers can't see what's rentable, at what
   price per day, or whether it's free on their dates. Availability
   lives in the owner's head.
2. **No booking** — every rental is a phone call + staff memory;
   there is nothing to sign in for, nothing to pay, nothing on a
   calendar.
3. **No handover/return** — stock falls via flows that don't exist;
   the only stock movement is the owner's **Add stock**. The
   inventory section (built in 005) is a dead end.

> Admin: "The restaurant is called Crates N' Plates and the system
> can't rent a crate."

## Plan references

- `docs/PLAN.md` §4 *Admin — Inventory* — "falls via rental
  handovers"; "the rentals catalog lands with issue 007."
- `docs/PLAN.md` §4 *Data layer* — **Rental stock** model listed;
  no booking / handover / return model.
- `docs/issues/005-…md` Resolution — stock ruling (owner): stock =
  rental units only; rentals feature sequenced as issue 007.

## Fix directions (decide some)

1. **Public rental catalog** — read-only catalog on `/`: item
   images, price per day, **per-date availability**; sign in with
   Google to book (mirrors ordering). Availability = stock vs
   date-range overlaps of confirmed bookings — same conflict math
   as reservations, never a mental ledger.
2. **Booking flow ≤3 inputs** — date range + quantities (steppers) →
   total computes itself → **GCash QR auto-sent** reusing
   payment-as-gate (proof attach, 006's amount/ref flag); prepaid
   in full. Rental bookings never touch the kitchen board.
3. **Counter handover + return** — staff **Hand over** at pickup
   (stock falls), **Confirm return** at the counter (stock rises —
   the flow-level return path Add stock never covered); return-due
   date with a quiet **overdue** staff row, no auto-penalty in v1.
4. **Admin rental items** — add/edit a rental item (name · image ·
   price/day · units) living in Inventory next to **Add stock**;
   extends the existing 005 surface, no new screen area.
5. **Optional: deposit instead of full prepay** — hold a deposit at
   booking, settle the rest at handover (split tender already
   exists at the POS).

## Acceptance criteria

- [x] A customer books a rental for chosen dates with ≤3 inputs and
      no staff retyping.
- [x] Availability is computed (stock vs overlapping confirmed
      bookings), never a mental ledger.
- [x] Stock moves both ways through the flow — handover down,
      confirmed return up — with Add stock reserved for purchases.

## Resolution (2026-09-22)

Directions 1–4 adopted; direction 5 declined — owner ruling: **full
prepay at booking**.

- **1 — catalog**: public read-only `/` catalog — image, price/day,
  **per-date availability** = stock vs date-range overlaps of
  confirmed bookings (reservation conflict math reused, never a
  mental ledger); respects the open/closed badge.
- **2 — booking**: date range + quantity steppers (2 input groups,
  ≤3 rule) → computed total → the **same GCash gate as orders**
  (proof attach, 006's amount/ref flag, 15-min window). Unpaid →
  booking lapses; units only move at handover, so stock is
  untouched. **Prepaid in full** — deposit split (dir 5) declined:
  one payment, zero new states, no partial-refund question.
- **3 — counter ops**: **Handover** (stock falls) / **Confirm
  return** (stock rises) as a POS task; return-due date + quiet
  **overdue** staff row, no auto-penalty in v1. Rentals never touch
  the kitchen board (invariant kept).
- **4 — admin**: rental items (name · image · price/day · units) in
  Inventory beside Add stock; bookings get their own date-range
  report view, **excluded from Top 3 / 7-day product trend**
  (rankings rank products).
- Stated defaults: **Rental booking** model lives off the `orders`
  stream — kitchen and sales order reads never see it.

- `docs/PLAN.md` — §1 **Rentals** bullet + role table ×3 + feature
  item 7; §4 new *Rentals* section; Inventory forward-ref ("lands
  with issue 007") resolved; Data layer models (**Rental booking**),
  Reports, Acceptance rentals beat.
- Charts: customer (catalog → book → handover/return branch),
  cashier (handover/return task), admin (rental items CRUD + rentals
  report).
- `CONTEXT.md` — new **Rentals** section: Rental booking, Handover,
  Return, Overdue.
- No ADR — additive; reverses no recorded rejection.
