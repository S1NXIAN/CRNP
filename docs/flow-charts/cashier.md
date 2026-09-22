# Cashier (POS) flowchart

One tap per online order on the happy path: **Mark served**.
Proof auto-verifies; only an amount/ref mismatch flags — one-tap
**Approve / Reject**, the exception.

```mermaid
flowchart TD
    A["Sign in — staff account<br/>/cashier (role-guarded)"] --> B{"Task?"}
    B --> C["Online pickup queue<br/>order code · screenshot auto-verified<br/>flag = amount/ref mismatch → Approve / Reject<br/>recently-verified list: at-leisure spot-check<br/>awaiting-proof row: in-window, no screenshot"]
    B --> D["Walk-in ring-up: tap tiles<br/>promo price + totals compute<br/>split tender: type one number"]
    B --> E["New reservation — type phone<br/>typeahead prefills name/party/type<br/>new customer: 2 steps (≤3 + ≤2)<br/>dine-in / function room / catering"]
    C -->|"flag: mismatch"| V{"Approve or Reject?"}
    V -->|"Reject"| R["Reject — confirm<br/>rejection record + image kept 7 days<br/>order dismissed, tracker flips"]
    V -->|"Approve"| F
    C -.->|"spot-check · no quota"| SV["Recently-verified list<br/>Reject reachable until Mark served"]
    SV -.->|"reject after the fact"| R
    C --> F["Settle / collect: split tender<br/>other tender + change compute"]
    D --> F
    F --> G["Receipt = one button"]
    G --> H["Mark served — the one happy-path tap<br/>kitchen ticket clears · tracker flips READY<br/>feeds sales + analytics"]
    H --> I["Customer collects with order code"]
    C -.->|"ready-for + 15 min / close:<br/>server clears it off NOW"| W["Ready — awaiting handover<br/>counter row · zero taps"]
    W -.->|"Mark served"| H
    W -.->|"close sweep: still uncollected"| U["Unclaimed — money kept<br/>manual note"]
    C -.->|"zero proof at 15:00"| X["Dismissed list (auto)<br/>Restore / Void"]
    X -.->|"Restore"| C
    E --> J{"Date-time conflict<br/>or duplicate?"}
    J -->|"no"| K["Confirmed → shared calendar"]
    J -->|"yes"| L["Rejected — blocked with reason"]
```
