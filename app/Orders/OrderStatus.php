<?php

declare(strict_types=1);

namespace App\Orders;

/**
 * An order's lifecycle state (PLAN §4 *Data layer* — `orders/{id}` carries the
 * status).
 *
 * A walk-in is `Pending` from ring-up until it is settled: it is not yet on the
 * kitchen board, so no food is cooked for a walk-in who leaves the counter. At
 * settle it becomes `Cooking` — the kitchen ticket appears the same instant —
 * and the counter hands it over. `Served` is the terminal state the online
 * pickup queue reaches via Mark served (#26).
 */
enum OrderStatus: string
{
    case Pending = 'pending';
    case Cooking = 'cooking';
    case Served = 'served';

    /**
     * Whether the kitchen board mirrors this status. Only a settled/verified,
     * unserved order is cookable, so `Pending` walk-ins stay off the board.
     */
    public function kitchenVisible(): bool
    {
        return $this === self::Cooking;
    }
}
