<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Business settings
    |--------------------------------------------------------------------------
    |
    | All wall-clock evaluation happens in the diner's timezone (the server
    | clock is UTC), so opening hours, scheduled closures, and the close sweep
    | agree. `cache_ttl` bounds a settings read no write has touched; every
    | settings write evicts it. `catalog_cache_ttl` is the backstop for the
    | shared catalog cache — a settings write (hours, force-close, closures,
    | banner) evicts that key immediately so an emergency close propagates on
    | the next request.
    |
    */

    'timezone' => env('BUSINESS_TIMEZONE', 'Asia/Manila'),

    'cache_ttl' => (int) env('SETTINGS_CACHE_TTL', 120),

    'catalog_cache_ttl' => (int) env('CATALOG_CACHE_TTL', 120),

];
