<?php

declare(strict_types=1);

namespace App\Orders\Actions;

use App\Cache\CashierQueueCache;
use App\Orders\HandledBy;
use App\Orders\Order;
use App\Orders\OrderStatus;
use App\Orders\OrderTracker;
use App\Rtdb\Contracts\RtdbClient;
use InvalidArgumentException;

/**
 * Voids a **Dismissed** order — ends it for good (PLAN §4 *Cashier POS* —
 * *Retired orders*).
 *
 * A Void is the other way out of the Dismissed list: it flips the order to the
 * terminal `voided` state and **never clears `refundOwed`** — money owed
 * survives until the admin's Mark refunded settles it. The write touches only
 * the status, the terminal `settledAt` stamp (the proof-cleanup sweep's
 * watermark), and the void instant, so any `refundOwed` booked on the order is
 * left exactly as it was.
 *
 * The tap is **status-guarded**: only a `Dismissed` order may be voided here. A
 * **Rejected** order's Void is #27's own state machine, so this action leaves it
 * alone; a stale tap on any other state is a friendly no-op.
 */
final readonly class VoidOrder
{
    public function __construct(
        private RtdbClient $client,
        private OrderTracker $tracker,
        private CashierQueueCache $queue,
    ) {}

    public function __invoke(string $orderId, HandledBy $handledBy): void
    {
        $node = $this->client->get("orders/{$orderId}");
        $order = is_array($node) ? Order::fromNode($orderId, $node) : null;

        if ($order === null) {
            throw new InvalidArgumentException("No order with id [{$orderId}].");
        }

        // Status-guarded: only a Dismissed order is voided here. A Rejected
        // order's Void belongs to #27; any stale tap writes nothing.
        if ($order->status !== OrderStatus::Dismissed) {
            return;
        }

        // `refundOwed` is deliberately absent: a void ends the order but never
        // clears the obligation.
        $voidedAt = now('UTC')->toIso8601ZuluString();

        $this->client->update("orders/{$orderId}", [
            'status' => OrderStatus::Voided->value,
            'voidedAt' => $voidedAt,
            'settledAt' => $voidedAt,
            'handledBy' => $handledBy->toNode(),
        ]);

        $this->tracker->forget($orderId);
        $this->queue->forget();
    }
}
