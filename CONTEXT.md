# Crates N' Plates

One diner system: customers order online for pickup, onsite customers are
rung up at the counter, the kitchen cooks off a read-only board.

## Language

### Orders

**Online order**:
A pickup order placed on the public site. GCash-only, identified by order code.
_Avoid_: delivery order, web order, e-commerce order

**Walk-in order**:
An onsite customer's order entered by the cashier at the POS. May carry a table name; settles by cash/GCash split tender.
_Avoid_: counter order, manual order, POS order

**Order code**:
The short code that identifies an online order at the counter. Replaces table names for online orders.
_Avoid_: order number, reference number, tracking ID

**Pickup time**:
When a customer asked to collect an online order; ASAP when they didn't.
_Avoid_: scheduled time, delivery slot, ETA

**Order tracker**:
The customer's live screen for their active online order — awaiting payment → verifying → cooking → READY. Bound to the session and account; the order code is shown, never typed.
_Avoid_: order status page, order history, tracking ID

### Payment lifecycle

**Payment window**:
The 15 minutes an online order has to be paid, starting when the GCash QR goes out. Ends in dismissal only when no proof was sent — an upload started inside the window holds.
_Avoid_: timeout, expiry period, order timer

**Payment verified**:
The order's GCash payment has been accepted — by the system on screenshot upload, unless the entered amount or GCash ref# mismatches, which holds it for the cashier's one-tap Approve/Reject. Only verified orders reach the kitchen.
_Avoid_: payment confirmed, paid status, payment approved

**Flagged hold**:
An order whose entered amount ≠ order total or whose GCash ref# is missing — parked for one Approve/Reject tap. The only thing that interrupts auto-verify; the kitchen waits on it.
_Avoid_: unconfirmed, pending review, 5-min flag

**Recently-verified list**:
Auto-verified orders kept for at-leisure spot-checking — no quota, no timer; Reject stays reachable until Mark served.
_Avoid_: audit queue, sampling queue, review queue

**Dismissed**:
An online order whose payment window expired unpaid. Auto-clears the active queues; retained for restore or void.
_Avoid_: cancelled, expired, rejected order

**Unclaimed**:
A paid, cooked order the customer never collected. Payment is kept — decided at the close sweep, never by the board's timer.
_Avoid_: no-show order, abandoned order, expired order

### Kitchen board

**NOW**:
The kitchen's oldest-first lane of cookable tickets — walk-ins and ASAP pickups.
_Avoid_: active tickets, live queue, main list

**LATER**:
Where scheduled pickups wait as dimmed rows until the server promotes them into NOW at pickup minus the cook lead.
_Avoid_: pending column, upcoming list, backlog

**Cook lead**:
The head start a scheduled pickup gets before its pickup time — the gap that lets the kitchen start it fresh.
_Avoid_: preparation time, lead time buffer, cook window

**Ready-for**:
When an order should be cooked and waiting — pickup time for scheduled pickups, verify + cook lead for ASAP, POS entry + cook lead for walk-ins.
_Avoid_: due time, target time, prep deadline

### Reservations

**Occupancy view**:
The public read-only count of seats taken vs capacity per slot — confirmed bookings only, aggregate, no names. It answers availability questions; it never books.
_Avoid_: availability calendar, booking calendar, live calendar

### Back office

**Restock**:
A receiving entry that raises stock — supplier, quantity, date. The only path that adds rental units.
_Avoid_: purchase order, replenishment, stock-in

**Scheduled closure**:
A date-range closure configured once that flips the site closed by itself; the force-close toggle stays for emergencies.
_Avoid_: holiday mode, blackout, temporary shutdown

### Sales ranking

**Top 3**:
The best-selling products over the rolling 7-day window, shown as a chip on product cards.
_Avoid_: best sellers tag, top products badge, trending
