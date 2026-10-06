<?php

declare(strict_types=1);

use App\Orders\CloseWindow;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled sweeps
|--------------------------------------------------------------------------
|
| The three sweeps from PLAN §4 *Data layer*, all evaluated in the diner's
| timezone (`Asia/Manila`; the server clock is UTC):
|
| - **Expiry** — every minute against `deadlineAt`, dismissing only zero-proof,
|   not-mid-upload orders.
| - **Close** — the tick runs every minute, but the {@see CloseWindow} filter
|   fires it only at the weekday closing hour (or while force-close is on). The
|   window lives in RTDB settings, so it is read at run time, never baked into
|   a cron string.
| - **Proof cleanup** — daily at end-of-day, deleting a proof 7 days after
|   `settledAt` / `rejectedAt`.
|
| Each entry dispatches a unique job, and every sweep has a manual fallback:
| run `php artisan orders:expire`, `orders:close`, or `proofs:cleanup` by hand
| if the scheduler is down.
|
*/

Schedule::command('orders:expire')
    ->everyMinute()
    ->timezone('Asia/Manila')
    ->name('orders:expire');

Schedule::command('orders:close')
    ->everyMinute()
    ->timezone('Asia/Manila')
    ->when(fn(): bool => app(CloseWindow::class)->isDueAt(now('Asia/Manila')))
    ->name('orders:close');

Schedule::command('proofs:cleanup')
    ->dailyAt('23:59')
    ->timezone('Asia/Manila')
    ->name('proofs:cleanup');
