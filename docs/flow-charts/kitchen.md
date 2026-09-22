# Kitchen flowchart

Read-only by design: zero input, zero login, zero cook interaction.
The server owns lanes, promotion, and expiry — the cook owns nothing.

```mermaid
flowchart TD
    A["Open /kitchen via secret URL<br/>no login · kiosk-level access"] --> N
    subgraph Board["Ticket board"]
        N["NOW — oldest first<br/>order code · age timer (red 12 min)<br/>NEW flash (genuine new, ~5 s)<br/>all-day = NOW only"]
        L["LATER — scheduled pickups<br/>full-size dimmed rows<br/>ready-for + countdown"]
    end
    L -->|"server promotes at pickup − 15 min<br/>or at verify (whichever is later)<br/>LATE shows immediately if overdue"| N
    N --> R["Auto-refresh 5–10 s + heartbeat<br/>board greys past ~15 s stale<br/>verified, unserved orders only"]
    R -.->|"continues while shift runs"| N
    S["Cashier marks an order served"] -.->|"ticket leaves NOW"| N
    X["Unclaimed pickup: ready-for + 15 min / close"] -.->|"server expires it → cashier no-show list"| N
    E["Cook never touches the board:<br/>no editing · no statuses · no session"] -.-> Board
```
