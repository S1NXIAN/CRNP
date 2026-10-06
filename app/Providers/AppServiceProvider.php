<?php

declare(strict_types=1);

namespace App\Providers;

use App\Auth\RtdbUserProvider;
use App\Export\ExportBackup;
use App\Rtdb\Contracts\RtdbClient;
use App\Rtdb\InMemoryRtdbClient;
use App\Rtdb\RestRtdbClient;
use App\Rtdb\ServiceAccountTokenProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(RtdbClient::class, function (): RtdbClient {
            if ($this->app->environment('testing')) {
                return new InMemoryRtdbClient();
            }

            /** @var string $baseUrl */
            $baseUrl = config('services.firebase.database_url');
            /** @var string $serviceAccountPath */
            $serviceAccountPath = config('services.firebase.service_account_path');
            /** @var string $tokenUri */
            $tokenUri = config('services.firebase.token_uri');
            /** @var int $tokenTtl */
            $tokenTtl = config('services.firebase.token_ttl');

            return new RestRtdbClient(
                $baseUrl,
                new ServiceAccountTokenProvider($serviceAccountPath, $tokenUri, $tokenTtl),
            );
        });

        // The export-backup writes its JSON to the local disk (the private
        // `storage/app` tree), which is not a secret store: bind the disk so the
        // service is testable against a faked filesystem.
        $this->app->bind(ExportBackup::class, function (): ExportBackup {
            return new ExportBackup(
                $this->app->make(RtdbClient::class),
                Storage::disk('local'),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Laravel's session guard remembers a signed-in customer by the Google
        // `sub`; this provider resolves that identifier back to `users/{uid}`
        // through the one persistence seam, so no SQL user table is needed.
        Auth::provider('rtdb', function (): RtdbUserProvider {
            return new RtdbUserProvider($this->app->make(RtdbClient::class));
        });

        // The real client IP sits behind the configured proxies, so the checkout
        // and login rate limits count per customer, not per proxy socket
        // (PLAN §4 *Auth & roles* — trusted proxy ranges configured).
        $proxies = config('trustedproxy.proxies');

        if (is_string($proxies) && $proxies !== '') {
            TrustProxies::at($proxies);
        }

        // Named rate limiters, numbers from PLAN §4 *Auth & roles*: checkout
        // 10/min per signed-in user, 30/min per real IP for anonymous traffic.
        RateLimiter::for('checkout', function (Request $request): Limit {
            $user = $request->user();

            if ($user instanceof \Illuminate\Contracts\Auth\Authenticatable) {
                $identifier = $user->getAuthIdentifier();

                return Limit::perMinute(10)->by(
                    'checkout:user:' . (is_scalar($identifier) ? (string) $identifier : ''),
                );
            }

            return Limit::perMinute(30)->by('checkout:ip:' . (string) $request->ip());
        });
    }
}
