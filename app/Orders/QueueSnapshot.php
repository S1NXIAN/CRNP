<?php

declare(strict_types=1);

namespace App\Orders;

/**
 * The cashier's online pickup queue: the sections the screen renders (PLAN §4
 * *Cashier POS*).
 *
 * - **flagged holds** — orders whose entered amount or ref# mismatched; the
 *   only thing that interrupts auto-verify. Both numbers are shown side by side
 *   for one-tap **Approve / Reject** (kitchen waits for the tap).
 * - **ready to pay** — prepaid, verified, unserved orders; Mark served is the
 *   one happy-path tap.
 * - **recently verified** — the same verified orders for at-leisure spot-check;
 *   Reject stays reachable until Mark served.
 * - **awaiting proof** — in-window orders with no screenshot yet, so staff see
 *   a customer waiting rather than a blank queue.
 * - **rejected** — its own state; the only actions are **Approve** (the undo)
 *   or **Void**, never Restore. The claimed refund is shown beside the total.
 *
 * The `signature` changes whenever a section's membership does, so the polling
 * client can reload the server-rendered page only when something moved. Every
 * row is scalar — the proof image never rides here.
 */
final readonly class QueueSnapshot
{
    /**
     * @param  list<QueueOrder>  $flaggedHolds
     * @param  list<QueueOrder>  $readyToPay
     * @param  list<QueueOrder>  $recentlyVerified
     * @param  list<QueueOrder>  $awaitingProof
     * @param  list<QueueOrder>  $rejected
     */
    public function __construct(
        public array $flaggedHolds,
        public array $readyToPay,
        public array $recentlyVerified,
        public array $awaitingProof,
        public array $rejected,
    ) {}

    public function signature(): string
    {
        return 'f:' . $this->ids($this->flaggedHolds)
            . ';r:' . $this->ids($this->readyToPay)
            . ';v:' . $this->ids($this->recentlyVerified)
            . ';a:' . $this->ids($this->awaitingProof)
            . ';x:' . $this->ids($this->rejected);
    }

    /**
     * @return array{
     *     flaggedHolds: list<array<string, mixed>>,
     *     readyToPay: list<array<string, mixed>>,
     *     recentlyVerified: list<array<string, mixed>>,
     *     awaitingProof: list<array<string, mixed>>,
     *     rejected: list<array<string, mixed>>,
     *     signature: string
     * }
     */
    public function toArray(): array
    {
        return [
            'flaggedHolds' => array_map(static fn(QueueOrder $order): array => $order->toArray(), $this->flaggedHolds),
            'readyToPay' => array_map(static fn(QueueOrder $order): array => $order->toArray(), $this->readyToPay),
            'recentlyVerified' => array_map(static fn(QueueOrder $order): array => $order->toArray(), $this->recentlyVerified),
            'awaitingProof' => array_map(static fn(QueueOrder $order): array => $order->toArray(), $this->awaitingProof),
            'rejected' => array_map(static fn(QueueOrder $order): array => $order->toArray(), $this->rejected),
            'signature' => $this->signature(),
        ];
    }

    /**
     * @param  list<QueueOrder>  $orders
     */
    private function ids(array $orders): string
    {
        return implode(',', array_map(static fn(QueueOrder $order): string => $order->id, $orders));
    }
}
