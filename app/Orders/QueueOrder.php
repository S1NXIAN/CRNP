<?php

declare(strict_types=1);

namespace App\Orders;

/**
 * A scalar projection of an order for the cashier's online pickup queue
 * (PLAN §4 *Cashier POS* — online pickup queue).
 *
 * The queue, list, tracker, and board reads match `orders/{id}` only, so they
 * are scalar **by construction** — the proof image lives in the sibling
 * `proofs/{id}` node and never rides here. This object carries only what the
 * queue screen renders.
 */
final readonly class QueueOrder
{
    public function __construct(
        public string $id,
        public string $code,
        public float $total,
        public ?string $deadlineAt,
        public ?string $placedAt,
    ) {}

    public static function fromOrder(Order $order): self
    {
        return new self(
            id: $order->id,
            code: $order->code,
            total: $order->total,
            deadlineAt: $order->deadlineAt,
            placedAt: $order->placedAt,
        );
    }

    /**
     * @return array{id: string, code: string, total: float, deadlineAt: ?string, placedAt: ?string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'total' => $this->total,
            'deadlineAt' => $this->deadlineAt,
            'placedAt' => $this->placedAt,
        ];
    }
}
