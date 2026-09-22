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

### Payment lifecycle

**Payment window**:
The 15 minutes an online order has to be paid, starting when the GCash QR goes out.
_Avoid_: timeout, expiry period, order timer

**Payment verified**:
The order's GCash payment has been accepted — by the system on screenshot upload, or kept under review until the cashier clears it. Only verified orders reach the kitchen.
_Avoid_: payment confirmed, paid status, payment approved

**Dismissed**:
An online order whose payment window expired unpaid. Auto-clears the active queues; retained for restore or void.
_Avoid_: cancelled, expired, rejected order

**Unclaimed**:
A paid, cooked order the customer never collected. Payment is kept.
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

### Sales ranking

**Top 3**:
The best-selling products over the rolling 7-day window, shown as a chip on product cards.
_Avoid_: best sellers tag, top products badge, trending
