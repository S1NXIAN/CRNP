<?php

declare(strict_types=1);

namespace App\Orders;

use App\Rtdb\Contracts\RtdbClient;
use App\Rtdb\RtdbQuery;

/**
 * Reads orders from `orders/{id}` (PLAN §4 *Data layer*).
 *
 * The kitchen board (#28) mirrors **verified, unserved** tickets only — a
 * walk-in becomes visible there at settle, never at the first tile tap — so
 * {@see kitchen()} is the one place that decides visibility. The rule lives
 * here rather than on the board so a walk-in and an online order share it.
 */
final readonly class Orders
{
    public function __construct(private RtdbClient $client) {}

    public function find(string $id): ?Order
    {
        $node = $this->client->get("orders/{$id}");

        return is_array($node) ? Order::fromNode($id, $node) : null;
    }

    /**
     * The order at $id, or a thrown {@see OrderNotFound}.
     *
     * The single load-and-parse seam every action routes through: {@see find()}'s
     * nullable read, promoted to a failure when a caller supplies an id that must
     * exist. A controller's 404 stays its own concern — this throw is for the
     * action layer.
     */
    public function require(string $id): Order
    {
        return $this->find($id) ?? throw OrderNotFound::for($id);
    }

    /**
     * The account's active order, from `users/{uid}/activeOrderId` (#23).
     *
     * The tracker reopens this with zero typing; a terminal order (served,
     * dismissed, rejected) is not active, so the customer falls back to the menu.
     */
    public function activeFor(string $uid): ?Order
    {
        $activeId = $this->client->get("users/{$uid}/activeOrderId");

        if (! is_string($activeId) || $activeId === '') {
            return null;
        }

        $order = $this->find($activeId);

        return $order !== null && $order->isActive() ? $order : null;
    }

    /**
     * The orders the kitchen board shows — settled/verified and not yet served,
     * keyed by id.
     *
     * @return array<string, Order>
     */
    public function kitchen(): array
    {
        $rows = $this->client->query(
            'orders',
            RtdbQuery::orderBy('status')->equalTo(OrderStatus::Cooking->value),
        );

        $orders = [];

        foreach ($rows as $id => $row) {
            $order = Order::fromNode((string) $id, $row);

            if ($order !== null && $order->status->kitchenVisible()) {
                $orders[(string) $id] = $order;
            }
        }

        return $orders;
    }
}
