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
 * **Approve** — the undo of a rejection (PLAN §4 *Cashier POS* — *Rejected*).
 *
 * From `rejected` the only actions are Approve or Void, never Restore. Approve
 * reverses the rejection: the obligation and the rejection markers are cleared
 * and the ticket **rejoins the board by age** — the original ready-for /
 * created time is kept, so the age timer is **never restarted** and the NEW
 * badge has no fresh instant to flash on. It rejoins as the verified/kitchen
 * state (`cooking`).
 *
 * The tap is **status-guarded**: only a `rejected` order may be approved, so a
 * stale or duplicate tap is a friendly no-op. The order's coalesced tracker
 * entry and the queue's coalesced entry are evicted so the tap propagates on
 * the next request rather than after the TTL.
 */
final readonly class ApproveOrder
{
    public function __construct(
        private RtdbClient $client,
        private OrderTracker $tracker,
        private CashierQueueCache $queue,
    ) {}

    public function __invoke(string $orderId): void
    {
        $order = $this->order($orderId);

        // Status-guarded: only a rejected order may be approved. A stale tap on
        // a hold, served, or dismissed order never flips it.
        if ($order->status !== OrderStatus::Rejected) {
            return;
        }

        // The undo clears the obligation and the rejection record. `rejectedAt`
        // and `refundOwed` are written as null (which deletes the RTDB key), and
        // no timestamp is touched — the ticket rejoins by its original age.
        $this->client->update("orders/{$orderId}", [
            'status' => OrderStatus::Cooking->value,
            'enteredAmount' => null,
            'refundOwed' => null,
            'rejectedAt' => null,
            'rejection' => null,
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
