<?php

declare(strict_types=1);

namespace App\Orders\Actions;

use App\Cache\CashierQueueCache;
use App\Orders\Order;
use App\Orders\OrderStatus;
use App\Orders\OrderTracker;
use App\Rtdb\Contracts\RtdbClient;
use InvalidArgumentException;

/**
 * **Void** an order — the terminal tap that ends a Rejected order (PLAN §4
 * *Cashier POS* — *Rejected*).
 *
 * Void flips the order to `voided` so it leaves every live read, and it **never
 * clears `refundOwed`** — a money obligation outlives the order and is settled
 * only by the admin's **Mark refunded** (#32). The Dismissed list gets the same
 * action in #29.
 *
 * The tap is **status-guarded**: only a `rejected` order may be voided, so a
 * stale or duplicate tap on a served, cooking, or already-voided order is a
 * friendly no-op. The order's coalesced tracker entry and the queue's coalesced
 * entry are evicted so the tap propagates on the next request.
 */
final readonly class VoidOrder
{
    public function __construct(
        private RtdbClient $client,
        private OrderTracker $tracker,
        private CashierQueueCache $queue,
    ) {}

    public function __invoke(string $orderId): void
    {
        $order = $this->order($orderId);

        // Status-guarded: only a rejected order may be voided in #27. A stale
        // tap never flips a live or terminal order. (#29 widens this to the
        // Dismissed list.)
        if ($order->status !== OrderStatus::Rejected) {
            return;
        }

        $this->client->update("orders/{$orderId}", [
            'status' => OrderStatus::Voided->value,
            'voidedAt' => now('UTC')->toIso8601ZuluString(),
        ]);

        $this->tracker->forget($orderId);
        $this->queue->forget();
    }

    private function order(string $orderId): Order
    {
        $node = $this->client->get("orders/{$orderId}");
        $order = is_array($node) ? Order::fromNode($orderId, $node) : null;

        if ($order === null) {
            throw new InvalidArgumentException("No order with id [{$orderId}].");
        }

        return $order;
    }
}
