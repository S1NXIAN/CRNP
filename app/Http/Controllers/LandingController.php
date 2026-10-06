<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

final class LandingController extends Controller
{
    /**
     * The public landing page. The menu and browse flow arrive with the
     * customer-ordering slice; the foundation serves the shell and the brand.
     */
    public function __invoke(): View
    {
        return view('welcome');
    }
}
