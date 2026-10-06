<?php

declare(strict_types=1);

namespace App\Auth;

use Illuminate\Http\RedirectResponse;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;
use Laravel\Socialite\Contracts\Provider as SocialiteProvider;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Two\AbstractProvider;
use RuntimeException;

/**
 * The seam over Laravel Socialite for the single Google sign-in flow.
 *
 * It owns the two things the app must get right about the OAuth exchange: the
 * stateful, PKCE-protected redirect, and reading the identity back off the
 * callback. Both are typed here so callers never touch Socialite's loose
 * factory contract.
 */
final readonly class GoogleOAuth
{
    /**
     * The scopes for a basic Google profile: openid, profile, email.
     *
     * @var list<string>
     */
    private const SCOPES = ['openid', 'profile', 'email'];

    public function __construct(private SocialiteFactory $socialite) {}

    /**
     * Begin the stateful, PKCE-protected Google redirect.
     */
    public function redirect(): RedirectResponse
    {
        return $this->oauthProvider()
            ->scopes(self::SCOPES)
            ->enablePKCE()
            ->redirect();
    }

    /**
     * The identity Google returns on the verified callback.
     */
    public function user(): SocialiteUser
    {
        return $this->provider()->user();
    }

    private function provider(): SocialiteProvider
    {
        return $this->socialite->driver('google');
    }

    private function oauthProvider(): AbstractProvider
    {
        $provider = $this->provider();

        if (! $provider instanceof AbstractProvider) {
            throw new RuntimeException('The [google] Socialite driver must be an OAuth 2 provider.');
        }

        return $provider;
    }
}
