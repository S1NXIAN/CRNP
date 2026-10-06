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

The **weekly export-backup** (PLAN §4 *Quota discipline*, rule 4) runs on the
same scheduler — `weeklyOn(1, '03:00')` in `Asia/Manila`. Its manual fallback is
`php artisan export:run`, which dispatches the same unique job. The export is
**incremental by `updatedAt`**, holding its watermark and checkpoint at
`export/state`, and its first run **bootstraps in bounded pages of 500 rows**
(checkpointed after every page, with the partial backup staged at
`exports/backup-current.json`), so a manual run after a long outage resumes
rather than re-reading the database. **Proofs are excluded** from the backup.

| Job | Scheduled | Manual fallback |
|---|---|---|
| Export-backup | weekly, Monday `03:00` | `php artisan export:run` |

Run `php artisan schedule:list` to confirm the entries and `php artisan
schedule:run` to drive them manually in a single pass. The scheduler itself is
driven by a single `php artisan schedule:work` (or a per-minute cron calling
`schedule:run`).
