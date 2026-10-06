<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * The admin shell.
 *
 * Dashboard, products, settings, and reports arrive with the later admin
 * slices; this slice establishes the role-guarded entry point.
 */
final class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.dashboard');
    }
}
