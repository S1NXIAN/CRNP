# 002 — Manual screenshot upload races the 15-min payment window

**Status:** open
**Severity:** critical (biggest manual chore in the "system-assisted" flow)
**Personas affected:** customer (Ease 6/10 driver), cashier

## Problem

Payment flow as planned: *pay GCash → export the confirmation screenshot →
return to the order screen → upload → auto-verify.* That is **three manual
steps under a countdown**. A blurry, slow, or forgotten screenshot means the
order is **Dismissed** at 15:00.

> Customer: "Manual, fiddly on a phone, and if it's blurry or slow I'm
> racing a **15-minute payment window** toward 'Order dismissed'."

This is the single largest human interaction left in the happy path — the
plan's goal is "system does the work, human only taps," yet the highest-
stakes step is fully manual.

## Plan references

- `docs/PLAN.md` §1 item 15 — "uploads the confirmation screenshot …
  **auto-verified** … no payment by 15:00 → **Dismissed**."
- `docs/PLAN.md` §2 — GCash QR research: `docs/research/gcash-qr-2026.md`.

## Fix directions (decide one)

1. **Retry-safe window** — extend or pause the window while a proof upload
   is attempted; Dismiss only after N failed attempts.
2. **Camera capture in-page** — one-tap "photograph receipt" instead of
   gallery-export-then-upload; fewer fumbles.
3. **GCash reference number + amount** (typed or pasted) instead of a
   screenshot — smaller payload, easier to auto-match; check GCash ToS.
4. **Verification buffer** — auto-verify is already default-yes; make
   *Dismissed* fire only when **no** upload exists (not when one is
   pending/rejected-but-retriable).

## Acceptance criteria

- [ ] A normal person can complete payment proof in **≤2 taps**.
- [ ] No legitimate payer gets Dismissed for a slow/blurry upload.
- [ ] Auto-verify path stays human-free on the happy path.
