<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Rtdb\Contracts\RtdbClient;
use App\Settings\SettingsRepository;
use App\Stats\DashboardMetrics;
use App\Stats\RefundsOwed;
use App\Stats\SalesLedger;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;

/**
 * The admin dashboard (PLAN §4 *Admin* — *Dashboard*).
 *
 * It shows the **Complete setup** banner until the GCash number and the QR image
 * are both configured, the **KPIs** (today's sales and orders), the **7-day
 * trend**, **best-sellers**, **peak hours**, the **last-backup badge** (read from
 * `export/state`) with a download link, and the **refunds-owed badge** counting
 * the outstanding obligations. Everything is computed server-side from the sales
 * ledger (bounded by `soldAt`); a rental booking (#34) never reaches the product
 * ranking or the trend.
 */
final class DashboardController extends Controller
{
    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly SalesLedger $ledger,
        private readonly RefundsOwed $refunds,
        private readonly RtdbClient $client,
    ) {}

    public function __invoke(): View
    {
        $settings = $this->settings->current();
        $now = CarbonImmutable::instance(now('UTC'));

        $sales = array_values(
            $this->ledger->between(
                $now->subDays(DashboardMetrics::WINDOW_DAYS)->toIso8601ZuluString(),
                $now->toIso8601ZuluString(),
            ),
        );

        $state = $this->client->get('export/state');

        $refunds = $this->refunds->all();
        $refundsTotal = 0.0;

        foreach ($refunds as $refund) {
            $refundsTotal += $refund['refundOwed'];
        }

        return view('admin.dashboard', [
            'settings' => $settings,
            'setupComplete' => $settings->hasPaymentBlock(),
            'metrics' => DashboardMetrics::fromSales($sales, $now),
            'refundsCount' => count($refunds),
            'refundsTotal' => round($refundsTotal, 2),
            'lastBackupAt' => is_array($state) && is_string($state['lastBackupAt'] ?? null) ? $state['lastBackupAt'] : null,
            'lastBackupPath' => is_array($state) && is_string($state['lastBackupPath'] ?? null) ? $state['lastBackupPath'] : null,
        ]);
    }
}
