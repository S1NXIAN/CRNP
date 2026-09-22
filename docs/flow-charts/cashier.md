# Cashier (POS) flowchart

One tap per online order on the happy path: **Mark served**.
Proof auto-verifies; only an amount/ref mismatch flags — one-tap
**Approve / Reject**, the exception. Every tap is status-guarded: a
stale or duplicate tap is a friendly no-op.

```mermaid
flowchart TD
    A["Sign in with Google — own staff account<br/>/cashier (role-guarded) · role read per request<br/>header names the cashier on duty<br/>Switch account = sign out → sign in<br/>every tap stamped handledBy"] --> B{"Task?"}
    B --> C["Online pickup queue — prepaid, no tender to settle<br/>order code · screenshot auto-verified<br/>flag = amount/ref mismatch → Approve / Reject<br/>recently-verified list: at-leisure spot-check<br/>awaiting-proof row: in-window, no screenshot"]
    B --> D["Walk-in ring-up: tap tiles<br/>promo price + totals compute<br/>order code drawn at ring-up (on receipt)"]
    B --> E["New reservation — type phone<br/>typeahead prefills name/party/type<br/>new customer: 2 steps (≤3 + ≤2)<br/>dine-in / function room / catering"]
    B --> Y["Rentals — handover / return<br/>Hand over → stock falls · Confirm return → stock rises<br/>overdue = quiet staff row, no auto-penalty"]
    C -->|"flag: mismatch"| V{"Approve or Reject?"}
    V -->|"Reject"| RJ["Rejected — its own state<br/>rejection record + image kept 7 days<br/>refund owed = entered amount, 'claimed'<br/>ticket leaves NOW · tracker: 'needs checking'"]
    RJ -->|"Approve (undo)"| RT["Ticket rejoins NOW by age<br/>timer never restarted · no NEW badge"]
    RJ -->|"Void"| VD["Voided — refund owed survives<br/>until admin Mark refunded"]
    V -->|"Approve"| H
    C -.->|"spot-check · no quota"| SV["Recently-verified list<br/>Reject reachable until Mark served"]
    SV -.->|"reject after the fact"| RJ
    C --> H["Mark served — the one happy-path tap<br/>writes the sale to sales/{id}<br/>kitchen ticket clears · tracker flips READY"]
    D --> F["Settle: type the cash handed — one number<br/>cash ≥ total → all-cash + change computes<br/>cash &lt; total → GCash = total − cash, ref# required<br/>Σ(payments) = total asserted"]
    F --> G["Receipt = one button<br/>(order code printed)"]
    G -.->|"ticket appears at settle"| K["Kitchen ticket — NOW<br/>order code · age timer"]
    K -.-> H
    H --> I["Customer collects with order code"]
    C -.->|"ready-for + 15 min / close:<br/>server clears it off NOW"| W["Ready — awaiting handover<br/>counter row · zero taps"]
    W -.->|"Mark served"| H
    W -.->|"close sweep: still uncollected"| U["Unclaimed — money kept<br/>manual note · counts as revenue"]
    U -.->|"customer returns with code:<br/>Collect late — no second sale write"| H
    C -.->|"zero proof at 15:00"| X["Dismissed list (auto)"]
    X -.->|"Restore: re-arms 15-min window<br/>+ audit entry"| C
    X -.->|"Void"| VD
    E --> J{"Date-time conflict<br/>or duplicate?"}
    J -->|"no"| L2["Confirmed → shared calendar"]
    J -->|"yes"| L["Rejected — blocked with reason"]
```
