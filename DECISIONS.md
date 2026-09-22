# DECISIONS.md — settled answers, organized by role

> Cheat sheet distilled from `docs/PLAN.md`. **PLAN.md is canonical** — if
> they disagree, PLAN.md wins and this file is stale; fix both in the same
> commit. Terms are defined in `CONTEXT.md`; hard-to-reverse choices live in
> `docs/adr/`. Format: **question → answer**, grouped by who the decision
> affects. Grep by role tag (`[shared]`, `[customer]`, `[cashier]`,
> `[kitchen]`, `[admin]`) or by keyword.

## Contents

1. [Shared — everyone](#1-shared--everyone) (stack, auth model, money, UX, gotchas)
2. [Customer — `/`](#2-customer--) (browse, sign-in, favorites, Top 3, checkout)
3. [Cashier — `/cashier`](#3-cashier--cashier) (POS, pickup queue, reservations)
4. [Kitchen — `/kitchen`](#4-kitchen--kitchen) (read-only display)
5. [Admin — `/admin`](#5-admin--admin) (promo, settings, inventory)

---

## 1. Shared — everyone `[shared]`

### Stack

- **Which framework?** Laravel 12 / PHP 8.2+, Blade + **plain CSS** — zero
  build step (no npm/Vite/Node anywhere).
- **Which database?** **Firebase RTDB, sole datastore.** No Eloquent/SQL —
  persistence goes through a thin RTDB service with `.indexOn` rules.
- **Online payment?** **GCash-only, pay-before-cook** — no Pay at Counter
  online, no cashier approval before the QR, screenshot auto-verifies
  (ADR-0001). Walk-ins keep cash/GCash split tender at the POS.
- **Where does it run?** Docker single `php:8.2-apache` locally + same image
  on **Render free** (14-min keepalive cron, `/health` route). Capstone must
  run **on the internet**, demonstrated end-to-end — not localhost-only.
- **Quality tooling?** Pint (lint), PHPStan/larastan (static), Pest (smoke).

### Rejected — don't let these creep back

React/Inertia/Next · Tailwind/Vite/any Node toolchain · Framer Motion/`motion`
(CSS + WAAPI only) · SQL alongside Firebase (two sources of truth) · Firebase
Auth · websockets/Reverb · OTP · Gmail API/email · email/password accounts ·
chat/contact · customer reservation portal · order status workflow · files
uploaded to disk (images are base64 in RTDB, resized ~800 px server-side;
Firebase Storage = production-only revisit) · cashier Approve-before-QR
gate · cook-side status/promotion toggles (server owns lanes).

### Money & labels — the system computes

- **Who does the math?** Nobody human. Promo prices, "% off" labels, stock
  totals, change due, open status, Top 3, report totals are all **derived**.
  Admin enters *either* a promo % *or* an exact price (`promoMode` +
  `promoValue`); the sibling value is computed — site and receipt can't
  disagree.

### Auth model (cross-role)

- **Who can browse?** Everyone — no login wall on the public site.
- **How do customers authenticate?** Only **Sign in with Google**
  (Socialite). No form, no OTP, no password, no email receipts. Session
  remembered → `users/{uid}` in RTDB.
- **How do staff authenticate?** Seeded accounts, session auth, role-guarded
  per URL prefix. No public staff signup.
- **Do customers write to RTDB directly?** No — everything is
  server-mediated through Laravel (their order + their own `users/{uid}`
  prefs only).
- **Anti-spam on checkout?** Route is `throttle`d per-IP (accounts alone
  don't stop scripted sign-ups).
- **How many taps does the system demand?** **One per online order
  (Mark served).** Default-yes, exception-only: the system acts first, the
  human taps only to object (GCash proof auto-verifies → **Reject** is the
  exception; no approval gate before the QR). Owner directive: fully
  system-assisted, minimal micro-interactions (ADR-0001).

### Orders (the one shared stream)

- **Two origins, one stream?** Yes — online (**pickup-only, GCash-only**,
  order code, no table field) + counter (cashier walk-in at the POS, split
  tender, table name added there) feed the same stream.
- **Status workflow / order history?** **None in v1** (no cook-maintained
  statuses). Lifecycle facts the *system* records: placed → payment window →
  verified → served; retired orders land in **Dismissed** (unpaid at 15:00,
  Restore/Void) or **Unclaimed** (paid, never collected, money kept).
- **Who gates payment?** **Nobody human.** No cashier approval before the
  QR — placing the order auto-sends it (15-min window). The customer's
  GCash screenshot **auto-verifies**; cashier taps **Reject** only on
  exception. Happy path = **one tap per order: Mark served** (ADR-0001).
- **What rides on an order?** **Order code** (identifies the pickup; no
  table field online) + **pickup time** (default ASAP).

### UX budgets (§3)

- **How many inputs/actions per screen?** ≤3 inputs; ≤3 primary actions + 1
  "More…" overflow; ≤9 visible commands per viewport.
- **Floors?** Targets ≥24×24 CSS px (cashier touch ≥44 px), reflow at 320 px,
  nav always visible and labeled (never an unlabeled icon).
- **Density?** Comfortable for public site + POS; compact only for read-only
  surfaces (kitchen, admin tables).
- **Confirms?** Every destructive action gets **one** reusable confirm
  pattern; every click confirms itself (toast + row updates in place).
- **Empty states?** Written, not default: "No orders waiting — line is clear."

### Gotchas

- **Timezone?** **`Asia/Manila`** — Render runs UTC; open/closed badge must
  evaluate in Manila time.
- **Stock rule?** Decrement **after** successful insert; restore by the
  Firebase key stored in the items array (never match by name), check status
  first to avoid double-restore.
- **Indexed queries?** Every queried field MUST be listed in
  `database.rules.json` `.indexOn`.
- **Timer constants?** Payment window **15 min** (starts at QR auto-send);
  cook lead **15 min** (LATER→NOW promotion, hardcoded v1); unclaimed
  auto-expire at ready-for **+15 min** / close; "unconfirmed" flag 5 min;
  heartbeat greys the board past ~15 s stale.

---

## 2. Customer — `/` `[customer]`

### Browse (no login)

- **Login wall on the menu?** No. Browse is open to everyone; menu, open/
  closed badge, announcement banner, computed prices all show signed-out.
- **Top 3 chip — what does it say?** Just **"Top 3"** — no "last 7 days"
  suffix, **no display gate** (appears as soon as 3 distinct products have a
  sale in the window). Ranking still uses a **rolling 7-day FIFO window**,
  re-ranked eagerly server-side on every sale write, cached at
  **`stats/top3`**; order = units sold → revenue → name; empty window → no
  tag; raw sales rows are never deleted.

### Favorites & prefs

- **Does the heart show when signed out?** **Yes, always visible** — no
  login wall on browse.
- **What happens on a signed-out tap?** Opens the **Google sign-in
  prompt** (not a local toggle). After sign-in it toggles and syncs
  cross-device via `users/{uid}`.
- **Saved add-on prefs?** Same rule — last selection pre-checked next visit,
  any device, requires sign-in.
- **Favorites page/filter?** **Not in v1** — hearts persist and sync, that's
  it.

### Checkout

- **What's required to order?** Live **Google session at POST** +
  **place order** (pickup time optional, default ASAP, earliest =
  now + 15 min) — then the **GCash QR auto-sends** (official image in a
  branded frame, never re-rendered — `docs/research/gcash-qr-2026.md`)
  and the **15-min payment window** starts. GCash-only online; **order
  code**, no table field.
- **After ordering?** Pay → upload GCash screenshot → **auto-verified**
  → kitchen ticket same instant. No payment at 15:00 → **Dismissed**:
  screen flips to "Order dismissed — no payment received" (the only
  notification channel — no email/push in v1), cashier queue
  auto-clears, order kept in the Dismissed list (Restore / Void).
  Paid, never collected → **Unclaimed**, money kept. Receipt prints at
  the counter — no email stack.

---

## 3. Cashier — `/cashier` `[cashier]`

- **How many taps per online order?** **One — Mark served** (default-yes
  design, ADR-0001): no approval gate, screenshot auto-verifies; cashier
  taps **Reject** only on exception (flagged "unconfirmed" 5 min).
- **Split tender?** Yes, for **walk-ins** — GCash + cash on one order: type
  **one** number, the other and the change compute themselves. Stored as
  `payments: [{method, amount}]`; receipt prints the breakdown.
- **Online pickup queue?** Orders arrive ready-to-pay (QR went out at
  placement) with order code + pickup time; **Dismissed list** (Restore /
  Void) absorbs unpaid-at-15:00 orders; **Unclaimed** note for paid,
  never-collected pickups.
- **Reservations — who enters them?** **Staff only** (dine-in / function
  room / catering) on a centralized calendar. **No customer self-serve
  portal** — it would race the staff calendar.
- **Conflict handling?** **Conflict + duplicate check before confirm**;
  rejected bookings blocked with a reason.
- **"Mark served"?** The single happy-path tap: clears the kitchen ticket
  and pushes the order into sales/analytics; receipt at the counter.

---

## 4. Kitchen — `/kitchen` `[kitchen]`

- **Any login?** **None** — shared-secret URL, kiosk-level access (that's
  the RBAC slot for "kitchen personnel").
- **Which orders appear?** **Verified, unserved only** — payment is the
  gate; there is no cashier approval step anywhere in the flow
  (ADR-0001). Unpaid orders never touch the board (they Dismiss instead).
- **Cook interaction?** **Zero:** no touch, no editing, no statuses, no
  drag-and-drop boards. The **server** owns lane promotion and expiry —
  never a cook tap.
- **Board shape?** **NOW** (oldest first: walk-ins + ASAP pickups; age
  timers **red at 12 min**; NEW flash = genuine new tickets only, ~5 s;
  **all-day = NOW only**) + **LATER** (scheduled pickups as dimmed rows,
  ready-for countdown; server promotes at `pickup − 15 min` **or**
  auto-verify, whichever is later — promoted ticket shows both clocks and
  reads **LATE immediately** if overdue).
- **How does it refresh?** Plain fetch every **5–10 s** — no websockets/
  Reverb — plus a **heartbeat**: board greys out past ~15 s stale so a dead
  screen never looks live. Shows **verified, unserved** orders only;
  **auto-clears** on Mark served; unclaimed pickups auto-expire to the
  cashier's no-show list at ready-for + 15 min / close.
- **Ticket contents?** **Order code** (no table names online) + age timer,
  NEW count, all-day counts.

---

## 5. Admin — `/admin` `[admin]`

- **Promo entry?** One **Add promo** button per product row → segmented
  `% / ₱` + one number + live preview → Save. Product form stays promo-free;
  remove = one click.
- **Opening hours?** Per weekday + **force-close override** (holidays /
  temporary closure) → drives the public open/closed badge (evaluated in
  `Asia/Manila`).
- **Images?** Server-side resize/compress → **base64 into RTDB** (Render's
  `uploads/` is ephemeral; Firebase Storage = production-only revisit).
- **Settings?** Announcement banner (show/hide), GCash number + official QR
  upload, receipt header, low-stock threshold (smart defaults, edit only
  what differs).
