<?php

declare(strict_types=1);

namespace App\Cache;

use Closure;
use Illuminate\Contracts\Cache\Repository;

/**
 * The `availability` cache entry — the coalesced read behind the public
 * occupancy view and (later, #34) the rental per-date availability (PLAN §4
 * *Quota discipline*, rule 2).
 *
 * The occupancy is computed from a date-bounded query, not a single node read,
 * so this seam caches the **computed result** under its own key rather than
 * routing through `RtdbClient::cached` (which reads one node). It is still a
 * Laravel cache entry, so a burst of visitors costs one computation per TTL.
 *
 * Reservation, capacity, rental, and stock writes evict it, so a booking
 * propagates on the next request rather than after the TTL backstop. It is safe
 * to be briefly stale because confirm re-checks the conflict server-side; the
 * TTL only governs how long a customer may chase a slot that filled. The entry
 * is a distinct key from the catalog and Top 3 entries — an availability write
 * never forces a base64 catalog re-read.
 */
final readonly class AvailabilityCache
{
    /** The occupancy/availability entry — its own key, never the catalog. */
    public const KEY = 'availability';

    /**
     * The TTL backstop for the nobody-edited case; writes are the propagation
     * path, so this only bounds reads no write has touched.
     */
    public const TTL_SECONDS = 60;

    public function __construct(private Repository $cache) {}

    /**
     * Serve the computed availability from the cache, computing it once per
     * TTL window on a miss.
     *
     * @template T
     * @param  Closure(): T  $compute
     * @return T
     */
    public function remember(Closure $compute): mixed
    {
        /** @var T $value */
        $value = $this->cache->remember(self::KEY, self::TTL_SECONDS, $compute);

        return $value;
    }

    /**
     * Evict the availability entry so the next occupancy read recomputes.
     */
    public function forget(): void
    {
        $this->cache->forget(self::KEY);
    }
}
