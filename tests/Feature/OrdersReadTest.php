<?php

declare(strict_types=1);

use App\Orders\OrderNotFound;
use App\Orders\Orders;

/**
 * The order read seam: {@see Orders::require()} is the load-and-parse every
 * action routes through, and its not-found branch is the one place a
 * caller-supplied missing id is turned into a failure.
 */

it('returns the order for an id that exists', function (): void {
    rtdb()->seed('orders/o1', ['code' => 'ORD1', 'type' => 'online', 'status' => 'cooking']);

    $order = app(Orders::class)->require('o1');

    expect($order->id)->toBe('o1')
        ->and($order->code)->toBe('ORD1');
});

it('throws OrderNotFound for an id that does not exist', function (): void {
    expect(fn() => app(Orders::class)->require('missing'))
        ->toThrow(OrderNotFound::class, 'No order with id [missing].');
});
