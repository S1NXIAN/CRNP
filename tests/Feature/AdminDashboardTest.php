<?php

declare(strict_types=1);

use App\Staff\StaffRole;

/**
 * #32 — the admin dashboard.
 *
 * The dashboard shows the diner's KPIs, a 7-day sales trend, best-sellers, peak
 * hours, the last-backup badge, and a refunds-owed badge counting the
 * outstanding `refundOwed`. Everything is computed server-side from the sales
 * ledger (bounded by `soldAt`) and the audit/order rows; rental bookings are
 * excluded from the product ranking and the trend.
 */

beforeEach(function (): void {
    seedStaff('owner@example.com', StaffRole::Admin, 'Owner Santos');
    signIn('owner@example.com', 'Owner Santos');

    // 2026-10-06 12:00 Asia/Manila is 04:00 UTC.
    $this->travelTo(new DateTimeImmutable('2026-10-06 12:00:00', new DateTimeZone('Asia/Manila')));
});

/**
 * One sale line, exactly as the ledger freezes it.
 *
 * @return array{productId: string, name: string, unitPrice: float, quantity: int, addOns: list<array{name: string, price: float}>}
 */
function dashboardLine(string $productId, string $name, float $unitPrice, int $quantity): array
{
    return [
        'productId' => $productId,
        'name' => $name,
        'unitPrice' => $unitPrice,
        'quantity' => $quantity,
        'addOns' => [],
    ];
}

/**
 * Arrange a sale row on the ledger.
 *
 * @param  list<array<string, mixed>>  $lines
 * @param  array<string, mixed>  $overrides
 */
function dashboardSale(string $id, string $soldAt, array $lines, float $total, array $overrides = []): string
{
    return seedSale($id, [
        'code' => strtoupper($id),
        'type' => 'walkin',
        'lines' => $lines,
        'total' => $total,
        'soldAt' => $soldAt,
        ...$overrides,
    ]);
}

it('shows the KPIs, the 7-day trend, best-sellers, and peak hours', function (): void {
    dashboardSale('a1', '2026-10-06T02:00:00Z', [dashboardLine('p1', 'Milktea', 100.0, 2)], 200.0);
    dashboardSale('a2', '2026-10-06T03:00:00Z', [dashboardLine('p2', 'Adobo', 100.0, 3)], 300.0);
    dashboardSale('a3', '2026-10-05T04:00:00Z', [dashboardLine('p3', 'Halo-halo', 100.0, 1)], 100.0);

    $this->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Sales today')
        ->assertSee('₱500.00')
        ->assertSee('Orders today')
        ->assertSee('7-day trend')
        ->assertSee('Best sellers')
        // Best seller by units over the window: Adobo (3).
        ->assertSee('Adobo')
        ->assertSee('Peak hours')
        ->assertSee('10:00');
});

it('shows the last-backup badge with the watermark from export/state', function (): void {
    rtdb()->seed('export/state', [
        'lastRun' => '2026-10-05T16:00:00Z',
        'lastBackupAt' => '2026-10-05T16:00:00Z',
        'lastBackupPath' => 'exports/backup.json',
    ]);

    $this->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Last backup')
        ->assertSee('2026-10-06')
        ->assertSee('Download');
});

it('downloads the last backup file', function (): void {
    \Illuminate\Support\Facades\Storage::fake('local');
    \Illuminate\Support\Facades\Storage::disk('local')->put('exports/backup.json', '{"sales":[]}');

    rtdb()->seed('export/state', [
        'lastBackupAt' => '2026-10-05T16:00:00Z',
        'lastBackupPath' => 'exports/backup.json',
    ]);

    $this->get(route('admin.backup.download'))->assertOk()->assertDownload('backup.json');
});

it('returns 404 when no backup exists yet', function (): void {
    \Illuminate\Support\Facades\Storage::fake('local');

    $this->get(route('admin.backup.download'))->assertNotFound();
});

it('shows "Never" on the last-backup badge before the export has run', function (): void {
    $this->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Last backup')
        ->assertSee('Never');
});

it('shows a refunds-owed badge counting the outstanding obligations', function (): void {
    seedAdminOrder('r1', ['refundOwed' => 350.0, 'rejectedAt' => '2026-10-06T03:00:00Z']);
    seedAdminOrder('r2', ['refundOwed' => 150.0, 'status' => 'voided', 'rejectedAt' => '2026-10-06T03:00:00Z']);

    $this->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Refunds owed')
        ->assertSee('₱500.00')
        ->assertSee('2');
});

it('excludes a rental booking from the 7-day product trend and best-sellers', function (): void {
    // A rental sale type is not an order sale; its 9-unit line and ₱4,500 total
    // must not reach the product ranking or the trend.
    seedSale('rent1', [
        'code' => 'RENT1',
        'type' => 'rental',
        'lines' => [dashboardLine('p9', 'Party Tent', 500.0, 9)],
        'total' => 4500.0,
        'soldAt' => '2026-10-06T02:00:00Z',
    ]);
    dashboardSale('o1', '2026-10-06T02:00:00Z', [dashboardLine('p1', 'Milktea', 100.0, 1)], 100.0);

    $this->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Milktea')
        ->assertDontSee('Party Tent')
        // Today's KPI is the order only, never the rental booking.
        ->assertSee('₱100.00')
        ->assertDontSee('4,500.00');
});

it('refuses the dashboard to a cashier and a signed-out visitor', function (): void {
    seedStaff('maria@example.com', StaffRole::Cashier, 'Maria Santos');

    signIn('maria@example.com', 'Maria Santos');
    $this->get(route('admin.dashboard'))->assertForbidden();

    auth()->logout();
    $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
});
