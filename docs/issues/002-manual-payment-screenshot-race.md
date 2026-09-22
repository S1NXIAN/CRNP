# 002 — Manual screenshot upload races the 15-min payment window

**Status:** fixed
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

- [x] A normal person can complete payment proof in **≤2 taps**.
- [x] No legitimate payer gets Dismissed for a slow/blurry upload.
- [x] Auto-verify path stays human-free on the happy path.

## Resolution (2026-09-22)

Chosen: **attach-from-recents + proof-hold** (revises direction 2,
adopts direction 4):

- **Attach-from-recents** — one **Attach payment screenshot** button →
  native picker opens on the newest image → selection auto-uploads,
  auto-verifies. Camera dropped: the GCash confirmation is on the
  *same* phone as the tracker, so "photograph it" points at the wrong
  device. In-page taps: 2 on Android, 3 on iOS (file-input sheet);
  the OS screenshot gesture sits outside every direction, including 3's
  typing.
- **Proof-hold** — the 15:00 sweep dismisses only when `proof` is
  null; an upload started before the window closes wins the race and
  holds the order. Reject-on-bogus still dismisses.

- `docs/PLAN.md` — §1 item 15, customer flow step 4; §4 proof /
  expiry bullets + pickup queue.
- `docs/flow-charts/customer.md`, `docs/flow-charts/cashier.md`
  updated (kitchen untouched).
- `CONTEXT.md` — Payment window term records the zero-proof rule.
- Direction 1 subsumed by proof-hold; direction 3 declined (typed ref
  needs a GCash transaction-matching capability or cashier eyeballing
  = human on the happy path, plus ToS work; violates click-first). No
  ADR — no recorded rejection reversed.
