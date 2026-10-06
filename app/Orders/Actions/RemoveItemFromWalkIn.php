<?php

declare(strict_types=1);

namespace App\Orders\Actions;

use App\Orders\LineItem;
use App\Orders\Order;
use App\Orders\OrderStatus;
use App\Rtdb\Contracts\RtdbClient;
use InvalidArgumentException;

/**
 * Removes a product line from a pending walk-in and recomputes the total.
 *
 * Removing the last line leaves a zero-total pending order, which settles
 * trivially; only a `pending` walk-in accepts the change.
 */
final readonly class RemoveItemFromWalkIn
{
    public function __construct(private RtdbClient $client) {}

    public function __invoke(string $orderId, string $productId): void
    {
        $node = $this->client->get("orders/{$orderId}");

        $order = is_array($node) ? Order::fromNode($orderId, $node) : null;

        if ($order === null) {
            throw new InvalidArgumentException("No order with id [{$orderId}].");
        }

        if ($order->status !== OrderStatus::Pending) {
            throw new InvalidArgumentException('This order is already settled.');
        }

        $lines = array_values(array_filter(
            $order->lines,
            static fn(LineItem $line): bool => $line->productId !== $productId,
        ));

        $total = 0.0;

        foreach ($lines as $line) {
            $total += $line->lineTotal();
        }

        $this->client->update("orders/{$orderId}", [
            'lines' => array_map(static fn(LineItem $line): array => $line->toNode(), $lines),
            'total' => round($total, 2),
        ]);
    }
}
