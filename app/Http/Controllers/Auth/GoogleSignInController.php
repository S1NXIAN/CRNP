<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Auth\Actions\ProvisionCustomer;
use App\Auth\GoogleIdentity;
use App\Auth\GoogleOAuth;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Contracts\User as SocialiteUser;

/**
 * The one "Sign in with Google" button: redirect to Google, then key the
 * returning identity by `sub` and remember it in the session.
 *
 * There is no sign-up form, no OTP, and no password reset — Google is the
 * only credential, and a first sign-in auto-provisions `users/{uid}`.
 */
final class GoogleSignInController extends Controller
{
    public function __construct(
        private readonly GoogleOAuth $google,
        private readonly ProvisionCustomer $provisionCustomer,
    ) {}

    /**
     * Send the visitor to Google with a stateful, PKCE-protected request.
     */
    public function redirect(): RedirectResponse
    {
        return $this->google->redirect();
    }

    /**
     * Verify Google's callback, provision or reuse `users/{uid}`, and sign in.
     */
    public function callback(): RedirectResponse
    {
        $customer = ($this->provisionCustomer)(
            $this->identityFrom($this->google->user()),
        );

        Auth::login($customer);

        return redirect()->intended(route('home'));
    }

    /**
     * Sign the customer out; the session keeps nothing else.
     */
    public function logout(): RedirectResponse
    {
        Auth::logout();

        return redirect()->route('home');
    }

    private function identityFrom(SocialiteUser $googleUser): GoogleIdentity
    {
        return new GoogleIdentity(
            sub: (string) $googleUser->getId(),
            email: $googleUser->getEmail(),
            name: $googleUser->getName(),
            avatar: $googleUser->getAvatar(),
        );
    }
}
