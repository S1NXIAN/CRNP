<?php

declare(strict_types=1);

namespace App\Orders;

/**
 * The cashier's **online pickup queue**: the sections the screen renders
 * (PLAN §4 *Cashier POS*).
 *
 * - **ready to pay** — prepaid, verified, unserved orders; Mark served is the
 *   one happy-path tap.
 * - **recently verified** — the same verified orders for at-leisure spot-check;
 *   Reject stays reachable until Mark served.
 * - **awaiting proof** — in-window orders with no screenshot yet, so staff see
 *   a customer waiting rather than a blank queue.
 * - **ready — awaiting handover** (#29) — tickets the server cleared off the
 *   kitchen board (ready-for + grace / close) that are paid but unserved, so
 *   nothing disappears without a trace; the cashier collects them with Mark
 *   served.
 * - **dismissed** (#29) — orders whose payment window lapsed with zero proof;
 *   Restore re-arms the window / Void ends the order.
 * - **unclaimed** (#29) — paid, cooked orders the customer never collected;
 *   Collect late serves them with no second sale.
 *
 * The `signature` changes whenever a section's membership does, so the polling
 * client can reload the server-rendered page only when something moved. Every
 * row is scalar — the proof image never rides here.
 */
final readonly class QueueSnapshot
{
    /**
     * @param  list<QueueOrder>  $readyToPay
     * @param  list<QueueOrder>  $recentlyVerified
     * @param  list<QueueOrder>  $awaitingProof
     * @param  list<QueueOrder>  $awaitingHandover
     * @param  list<QueueOrder>  $dismissed
     * @param  list<QueueOrder>  $unclaimed
     */
    public function __construct(
        public array $readyToPay,
        public array $recentlyVerified,
        public array $awaitingProof,
        public array $awaitingHandover = [],
        public array $dismissed = [],
        public array $unclaimed = [],
    ) {}

    public function signature(): string
    {
        return 'r:' . $this->ids($this->readyToPay)
            . ';v:' . $this->ids($this->recentlyVerified)
            . ';a:' . $this->ids($this->awaitingProof)
            . ';h:' . $this->ids($this->awaitingHandover)
            . ';d:' . $this->ids($this->dismissed)
            . ';u:' . $this->ids($this->unclaimed);
    }

    /**
     * @return array{
     *     readyToPay: list<array<string, mixed>>,
     *     recentlyVerified: list<array<string, mixed>>,
     *     awaitingProof: list<array<string, mixed>>,
     *     awaitingHandover: list<array<string, mixed>>,
     *     dismissed: list<array<string, mixed>>,
     *     unclaimed: list<array<string, mixed>>,
     *     signature: string
     * }
     */
    public function toArray(): array
    {
        return [
            'readyToPay' => $this->rows($this->readyToPay),
            'recentlyVerified' => $this->rows($this->recentlyVerified),
            'awaitingProof' => $this->rows($this->awaitingProof),
            'awaitingHandover' => $this->rows($this->awaitingHandover),
            'dismissed' => $this->rows($this->dismissed),
            'unclaimed' => $this->rows($this->unclaimed),
            'signature' => $this->signature(),
        ];
    }

    /**
     * @param  list<QueueOrder>  $orders
     * @return list<array<string, mixed>>
     */
    private function rows(array $orders): array
    {
        return array_map(static fn(QueueOrder $order): array => $order->toArray(), $orders);
    }

    /**
     * @param  list<QueueOrder>  $orders
     */
    private function ids(array $orders): string
    {
        return implode(',', array_map(static fn(QueueOrder $order): string => $order->id, $orders));
    }
}
