<?php

declare(strict_types=1);

namespace App\Orders;

use App\Rtdb\Contracts\RtdbClient;
use App\Rtdb\RtdbQuery;
use Carbon\CarbonImmutable;

/**
 * The cashier's **online pickup queue** (PLAN §4 *Cashier POS*).
 *
 * Prepaid online orders arrive **ready-to-pay** with no approval tap — the QR
 * went out at placement and the screenshot auto-verifies — and the same
 * verified, unserved orders form the **recently-verified list** for at-leisure
 * spot-check, where Reject stays reachable until Mark served. In-window orders
 * with **no screenshot yet** show as **awaiting proof**, so staff see a customer
 * waiting rather than a blank queue.
 *
 * The read is two status-bounded queries (`orders/.indexOn status`), and each
 * row is a scalar projection of `orders/{id}` — the proof image lives in the
 * sibling `proofs/{id}` node and never rides here, so the queue is scalar by
 * construction (PLAN §4 *Quota discipline*, rule 3). Only online orders are in
 * scope: a walk-in is rung up and handed over at the counter, never through the
 * online queue.
 *
 * The computed snapshot is served from a coalesced cache entry whose TTL equals
 * {@see self::POLL_SECONDS} (rule 1), so N registers cost one RTDB read per
 * interval; Mark served evicts the entry so the cashier's own tap propagates on
 * the next request rather than after the TTL.
 */
final readonly class CashierQueue
{
    /**
     * The poll cadence the queue screen fetches on, and the coalesced cache TTL
     * — the entry is never older than the cadence the client already accepts.
     */
    public const POLL_SECONDS = 5;

    public function __construct(private RtdbClient $client) {}

    public function snapshot(): QueueSnapshot
    {
        $verified = $this->verifiedUnserved();
        $at = CarbonImmutable::instance(now('UTC'));

        return new QueueSnapshot(
            readyToPay: $verified,
            recentlyVerified: $verified,
            awaitingProof: $this->awaitingProof($at),
        );
    }

    /**
     * Prepaid, verified, unserved online orders — the ready-to-pay and
     * recently-verified sections share this set. Auto-verification is what puts
     * an order here, so there is no approval tap; Mark served is the one
     * happy-path tap off it.
     *
     * @return list<QueueOrder>
     */
    private function verifiedUnserved(): array
    {
        $orders = $this->online(
            $this->client->query(
                'orders',
                RtdbQuery::orderBy('status')->equalTo(OrderStatus::Cooking->value),
            ),
        );

        usort($orders, static fn(Order $a, Order $b): int => self::moment($a->placedAt) <=> self::moment($b->placedAt));

        return array_map(static fn(Order $order): QueueOrder => QueueOrder::fromOrder($order), $orders);
    }

    /**
     * In-window online orders with no screenshot yet — a customer mid-payment,
     * not a blank queue. A lapsed window (the expiry sweep's concern) or an
     * order that already has a proof drops out.
     *
     * @return list<QueueOrder>
     */
    private function awaitingProof(CarbonImmutable $at): array
    {
        $orders = $this->online(
            $this->client->query(
                'orders',
                RtdbQuery::orderBy('status')->equalTo(OrderStatus::AwaitingPayment->value),
            ),
        );

        $awaiting = [];

        foreach ($orders as $order) {
            if ($order->proofReceivedAt !== null || $order->deadlineAt === null) {
                continue;
            }

            if (CarbonImmutable::parse($order->deadlineAt)->greaterThan($at)) {
                $awaiting[] = $order;
            }
        }

        usort($awaiting, static fn(Order $a, Order $b): int => self::moment($a->deadlineAt) <=> self::moment($b->deadlineAt));

        return array_map(static fn(Order $order): QueueOrder => QueueOrder::fromOrder($order), $awaiting);
    }

    /**
     * The online orders of a query result, skipping a row that cannot be parsed
     * or that belongs to the walk-in stream.
     *
     * @param  array<string, array<string, mixed>>  $rows
     * @return list<Order>
     */
    private function online(array $rows): array
    {
        $orders = [];

        foreach ($rows as $id => $row) {
            $order = Order::fromNode((string) $id, $row);

            if ($order === null || $order->type !== OrderType::Online->value) {
                continue;
            }

            $orders[] = $order;
        }

        return $orders;
    }

    /**
     * A sortable instant for an ISO-8601 string, with a missing one sorted last.
     */
    private static function moment(?string $iso): int
    {
        return $iso !== null ? CarbonImmutable::parse($iso)->getTimestamp() : PHP_INT_MAX;
    }
}
