# Operations

The three sweeps (PLAN §4 *Data layer*) run on the Laravel scheduler in
`Asia/Manila` via `routes/console.php`. Each has a **manual fallback** for when
the scheduler is down: run the command by hand and it dispatches the same unique
job, so a manual run racing a scheduled tick cannot double-write.

| Sweep | Scheduled | Manual fallback |
|---|---|---|
| Expiry | `everyMinute` | `php artisan orders:expire` |
| Close | every minute, gated to the weekday closing hour / force-close | `php artisan orders:close` |
| Proof cleanup | daily `23:59` | `php artisan proofs:cleanup` |

Run `php artisan schedule:list` to confirm the entries and `php artisan
schedule:run` to drive them manually in a single pass. The scheduler itself is
driven by a single `php artisan schedule:work` (or a per-minute cron calling
`schedule:run`).
