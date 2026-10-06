<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * The sign-in prompt: the single "Sign in with Google" button a gated action
 * surfaces, and the landing pad for a signed-out customer.
 */
final class SignInPromptController extends Controller
{
    public function __invoke(): View
    {
        return view('auth.login');
    }
}
