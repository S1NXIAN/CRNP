<?php

declare(strict_types=1);

namespace App\Catalog;

/**
 * The one catalog cache entry the customer site (`/` menu + product pages)
 * reads through — its TTL is only a backstop for the nobody-edited case.
 *
 * The propagation path is eviction: any admin **product** write (#21) *and*
 * any **settings** write (hours, force-close, closures, banner) forgets this
 * key, so a force-close reaches the next request rather than waiting out a
 * TTL. The key and TTL live here so both slices share one definition.
 */
final class CatalogCache
{
    public const KEY = 'catalog';

    public static function ttl(): int
    {
        $ttl = config('settings.catalog_cache_ttl');

        return is_numeric($ttl) ? (int) $ttl : 120;
    }
}
