<?php

declare(strict_types=1);

namespace App\Orders\Actions;

use App\Orders\HandledBy;
use App\Orders\OrderCode;
use App\Orders\OrderStatus;
use App\Orders\OrderType;
use App\Rtdb\Contracts\RtdbClient;
use Illuminate\Support\Str;

/**
 * Opens a walk-in at ring-up: draws its order code and persists a `pending`
 * order with an empty cart.
 *
 * The code is drawn now — the same code space as online orders — so it is
 * printed on the receipt and carried onto the kitchen ticket. The order stays
 * `pending` (off the kitchen board) until it is settled, so no food is cooked
 * for a walk-in who leaves the counter (PLAN §4 *Cashier POS*). The creating
 * cashier is stamped `handledBy`.
 */
final readonly class OpenWalkIn
{
    public function __construct(private RtdbClient $client) {}

    public function __invoke(HandledBy $handledBy): string
    {
        $id = (string) Str::ulid();

        $this->client->set("orders/{$id}", [
            'code' => OrderCode::draw()->value,
            'type' => OrderType::WalkIn->value,
            'status' => OrderStatus::Pending->value,
            'lines' => [],
            'total' => 0.0,
            'payments' => [],
            'change' => 0.0,
            'placedAt' => now('UTC')->toIso8601ZuluString(),
            'handledBy' => $handledBy->toNode(),
        ]);

        return $id;
    }
}
