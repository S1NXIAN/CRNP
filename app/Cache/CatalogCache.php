<?php

declare(strict_types=1);

namespace App\Cache;

use App\Rtdb\Contracts\RtdbClient;

/**
 * The `catalog` cache entry — the coalesced read behind the customer menu and
 * product pages (PLAN §4 *Quota discipline*, rule 2).
 *
 * Product, category, and (later) settings writes evict it, so a price, promo,
 * visibility, category, add-on, or image change propagates on the next request
 * rather than after the TTL backstop. The settings slice (#20) shares this key.
 */
final readonly class CatalogCache
{
    public const KEY = 'catalog';

    /**
     * The TTL backstop for the nobody-edited case; writes are the propagation
     * path, so this only bounds reads no write has touched.
     */
    public const TTL_SECONDS = 300;

    public function __construct(private RtdbClient $client) {}

    /**
     * Evict the catalog entry so the next menu read re-reads RTDB.
     */
    public function forget(): void
    {
        $this->client->forget(self::KEY);
    }
}
