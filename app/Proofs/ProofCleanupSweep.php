<?php

declare(strict_types=1);

namespace App\Proofs;

use App\Rtdb\Contracts\RtdbClient;
use App\Rtdb\RtdbQuery;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The **proof cleanup** sweep (PLAN §4 *Data layer* — proof lifecycle).
 *
 * Runs daily at end-of-day (`Asia/Manila`). A GCash screenshot is evidence, not
 * a durable record: the sweep **deletes** the `proofs/{id}` node **7 days** after
 * a terminal order's `settledAt`, 7 days after a rejected proof's `rejectedAt`
 * (watchdog evidence), and 7 days after a **rental booking's** `terminalAt`
 * (`proofs/rental-{id}`; the booking row is left intact). The order row, the
 * rejection record, the rental booking, and the sale are left untouched — only
 * the image bytes go, so the ledger keeps its scalar history and the image
 * storage stays inside the free tier.
 *
 * All three reads are bounded by an index **and by an age window** (PLAN §4
 * *Quota discipline*, rule 3): each run touches only the nodes that crossed the
 * 7-day threshold since the last run, never every proof ever stored. A daily
 * cadence means the one-day window is ample; the documented manual fallback
 * widens it to recover from a scheduler outage.
 */
final readonly class ProofCleanupSweep
{
    /**
     * The age after which a proof image is deleted.
     */
    public const RETAIN_DAYS = 7;

    /**
     * The width of the age window each run scans. Only bounds the read — a daily
     * run crosses every proof exactly once, so a day is ample and the manual
     * fallback widens it after an outage.
     */
    public const DEFAULT_WINDOW_DAYS = 2;

    public function __construct(private RtdbClient $client) {}

    /**
     * Delete every proof that crossed the retention window.
     *
     * @param  int|null  $windowDays  widen the range to recover from a
     *                                scheduler outage; defaults to two days
     * @return list<string> the order ids whose proof was deleted
     */
    public function __invoke(?CarbonInterface $now = null, ?int $windowDays = null): array
    {
        $at = CarbonImmutable::instance($now ?? now('UTC'));
        $cutoff = $at->subDays(self::RETAIN_DAYS);
        $window = $windowDays !== null && $windowDays > 0 ? $windowDays : self::DEFAULT_WINDOW_DAYS;
        $floor = $cutoff->subDays($window)->toIso8601ZuluString();
        $ceiling = $cutoff->toIso8601ZuluString();

        $deleted = [];

        // Terminal orders: 7 days after settledAt.
        $settled = $this->client->query(
            'orders',
            RtdbQuery::orderBy('settledAt')->range($floor, $ceiling),
        );

        foreach ($settled as $id => $row) {
            if ($this->deleteProof((string) $id)) {
                $deleted[] = (string) $id;
            }
        }

        // Rejected proofs: 7 days after rejectedAt, even on an order whose
        // settledAt is absent (a rejected order never settles).
        $rejected = $this->client->query(
            'proofs',
            RtdbQuery::orderBy('rejectedAt')->range($floor, $ceiling),
        );

        foreach ($rejected as $id => $row) {
            if ($this->deleteProof((string) $id)) {
                $deleted[] = (string) $id;
            }
        }

        // Rental proofs: 7 days after the booking reached a terminal state
        // (returned / lapsed / cancelled), keyed off its `terminalAt`. The node
        // lives at `proofs/rental-{id}`; the booking record is left intact.
        $rental = $this->client->query(
            'rentalBookings',
            RtdbQuery::orderBy('terminalAt')->range($floor, $ceiling),
        );

        foreach ($rental as $id => $row) {
            $proofId = "rental-{$id}";

            if ($this->deleteProof($proofId)) {
                $deleted[] = $proofId;
            }
        }

        return array_values(array_unique($deleted));
    }

    /**
     * Delete the proof node for a proof id, reporting whether one existed.
     */
    private function deleteProof(string $proofId): bool
    {
        if ($this->client->get("proofs/{$proofId}") === null) {
            return false;
        }

        $this->client->delete("proofs/{$proofId}");

        return true;
    }
}
