<?php

declare(strict_types=1);

namespace App\Providers;

use App\Rtdb\Contracts\RtdbClient;
use App\Rtdb\InMemoryRtdbClient;
use App\Rtdb\RestRtdbClient;
use App\Rtdb\ServiceAccountTokenProvider;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
