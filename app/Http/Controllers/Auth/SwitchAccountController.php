<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * "Switch account" on the register: sign the current cashier out, then land on
 * the one Google sign-in button so the next person signs in as themselves.
 *
 * There is no account picker and no shared login — the trail stays honest
 * because a shared register still records known hands.
 */
final class SwitchAccountController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        Auth::logout();

        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login');
    }
}
