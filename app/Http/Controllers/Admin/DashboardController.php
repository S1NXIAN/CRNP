<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Settings\SettingsRepository;
use Illuminate\Contracts\View\View;

/**
 * The admin shell.
 *
 * The dashboard shows the **Complete setup** banner until the GCash number and
 * the official QR image are both configured — ordering stays gated behind the
 * same condition on the customer site.
 */
final class DashboardController extends Controller
{
    public function __construct(private readonly SettingsRepository $settings) {}

    public function __invoke(): View
    {
        $settings = $this->settings->current();

        return view('admin.dashboard', [
            'settings' => $settings,
            'setupComplete' => $settings->hasPaymentBlock(),
        ]);
    }
}
