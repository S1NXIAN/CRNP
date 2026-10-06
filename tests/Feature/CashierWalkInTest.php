<?php

declare(strict_types=1);

use App\Orders\Orders;
use App\Staff\StaffDirectory;
use App\Staff\StaffRole;

beforeEach(function (): void {
    seedStaff('maria@example.com', StaffRole::Cashier, 'Maria Santos');
    seedCategory('drinks', 'Drinks');

    signIn('maria@example.com', 'Maria Santos');
});

/**
 * Open a walk-in through the register and return its order id.
 */
function openWalkIn(): string
{
    test()->post(route('cashier.walkins.store'))->assertRedirect();

    $orders = rtdb()->get('orders');

    expect($orders)->toBeArray()->toHaveCount(1);

    return (string) array_key_first($orders);
}

function addItem(string $orderId, string $productId, int $quantity = 1): void
{
    test()->post(
        route('cashier.walkins.items.store', ['walkin' => $orderId]),
        ['product' => $productId, 'quantity' => $quantity],
    )->assertRedirect(route('cashier.walkins.show', ['walkin' => $orderId]));
}

it('builds a walk-in with the computed promo price and totals', function (): void {
    seedProduct('p1', ['name' => 'Milktea', 'price' => 250.0, 'promoMode' => 'percent', 'promoValue' => 20, 'promoPrice' => 200.0]);

    $orderId = openWalkIn();
    addItem($orderId, 'p1', 2);

    $node = rtdb()->get("orders/{$orderId}");

    expect($node['lines'][0])->toMatchArray([
        'productId' => 'p1',
        'name' => 'Milktea',
        'unitPrice' => 200.0,
        'quantity' => 2,
    ])->and($node['total'])->toBe(400.0);

    $this->get(route('cashier.walkins.show', ['walkin' => $orderId]))
        ->assertOk()
        ->assertSee('₱200.00')
        ->assertSee('₱400.00');
});

it('draws an order code at ring-up', function (): void {
    seedProduct('p1');

    $orderId = openWalkIn();

    $code = rtdb()->get("orders/{$orderId}")['code'];

    expect($code)->toBeString()->not->toBe('');

    $this->get(route('cashier.walkins.show', ['walkin' => $orderId]))
        ->assertOk()
        ->assertSee((string) $code);
});

it('keeps the walk-in off the kitchen board until it is settled', function (): void {
    seedProduct('p1', ['price' => 200.0]);

    $orderId = openWalkIn();
    addItem($orderId, 'p1');

    expect(rtdb()->get("orders/{$orderId}")['status'])->toBe('pending')
        ->and(app(Orders::class)->kitchen())->toBe([]);

    $this->post(route('cashier.walkins.settle', ['walkin' => $orderId]), ['cash' => '200'])
        ->assertRedirect(route('cashier.walkins.receipt', ['walkin' => $orderId]));

    expect(rtdb()->get("orders/{$orderId}")['status'])->toBe('cooking')
        ->and(app(Orders::class)->kitchen())->toHaveKey($orderId);
});

it('settles cash at or above the total as all-cash with computed change', function (): void {
    seedProduct('p1', ['price' => 200.0]);

    $orderId = openWalkIn();
    addItem($orderId, 'p1');

    $this->post(route('cashier.walkins.settle', ['walkin' => $orderId]), ['cash' => '250'])
        ->assertRedirect(route('cashier.walkins.receipt', ['walkin' => $orderId]));

    $node = rtdb()->get("orders/{$orderId}");

    expect($node['payments'])->toBe([['method' => 'cash', 'amount' => 200.0]])
        ->and($node['change'])->toBe(50.0)
        ->and($node['gcashRef'] ?? null)->toBeNull();
});

it('splits the tender when cash is under the total and requires a ref#', function (): void {
    seedProduct('p1', ['price' => 200.0]);

    $orderId = openWalkIn();
    addItem($orderId, 'p1');

    // A GCash leg without a ref# is refused at the boundary; the order is untouched.
    $this->post(route('cashier.walkins.settle', ['walkin' => $orderId]), ['cash' => '100'])
        ->assertSessionHasErrors('gcashRef');

    expect(rtdb()->get("orders/{$orderId}")['status'])->toBe('pending');

    $this->post(route('cashier.walkins.settle', ['walkin' => $orderId]), ['cash' => '100', 'gcashRef' => '1234567890'])
        ->assertRedirect(route('cashier.walkins.receipt', ['walkin' => $orderId]));

    $node = rtdb()->get("orders/{$orderId}");

    expect($node['payments'])->toBe([
        ['method' => 'cash', 'amount' => 100.0],
        ['method' => 'gcash', 'amount' => 100.0],
    ])->and($node['change'])->toBe(0.0)
        ->and($node['gcashRef'])->toBe('1234567890');
});

it('stamps handledBy on the walk-in order and its sale row', function (): void {
    seedProduct('p1', ['price' => 200.0]);

    $orderId = openWalkIn();
    addItem($orderId, 'p1');

    $this->post(route('cashier.walkins.settle', ['walkin' => $orderId]), ['cash' => '200']);

    $expected = ['uid' => StaffDirectory::idForEmail('maria@example.com'), 'name' => 'Maria Santos'];

    expect(rtdb()->get("orders/{$orderId}")['handledBy'])->toBe($expected);

    $sale = rtdb()->get("sales/{$orderId}");

    expect($sale)->toBeArray()
        ->and($sale['total'])->toBe(200.0)
        ->and($sale['handledBy'])->toBe($expected)
        ->and($sale['code'])->toBe(rtdb()->get("orders/{$orderId}")['code']);
});

it('prints a receipt with the breakdown and order code', function (): void {
    seedProduct('p1', ['name' => 'Milktea', 'price' => 200.0]);

    $orderId = openWalkIn();
    addItem($orderId, 'p1');

    $this->post(route('cashier.walkins.settle', ['walkin' => $orderId]), ['cash' => '100', 'gcashRef' => '999']);

    $code = rtdb()->get("orders/{$orderId}")['code'];

    $this->get(route('cashier.walkins.receipt', ['walkin' => $orderId]))
        ->assertOk()
        ->assertSee((string) $code)
        ->assertSee('Milktea')
        ->assertSee('Cash')
        ->assertSee('GCash')
        ->assertSee('₱100.00')
        ->assertSee('Print receipt');
});

it('recomputes the total when an item is removed', function (): void {
    seedProduct('p1', ['name' => 'Milktea', 'price' => 200.0]);
    seedProduct('p2', ['name' => 'Pandesal', 'price' => 50.0]);

    $orderId = openWalkIn();
    addItem($orderId, 'p1');
    addItem($orderId, 'p2');

    expect(rtdb()->get("orders/{$orderId}")['total'])->toBe(250.0);

    $this->delete(route('cashier.walkins.items.destroy', ['walkin' => $orderId, 'product' => 'p1']))
        ->assertRedirect(route('cashier.walkins.show', ['walkin' => $orderId]));

    expect(rtdb()->get("orders/{$orderId}")['total'])->toBe(50.0);
});

it('guards settle so a second tap is a friendly no-op', function (): void {
    seedProduct('p1', ['price' => 200.0]);

    $orderId = openWalkIn();
    addItem($orderId, 'p1');

    $this->post(route('cashier.walkins.settle', ['walkin' => $orderId]), ['cash' => '200'])
        ->assertRedirect(route('cashier.walkins.receipt', ['walkin' => $orderId]));

    // The order is no longer pending, so a stale second settle does not flip it.
    $this->post(route('cashier.walkins.settle', ['walkin' => $orderId]), ['cash' => '500'])
        ->assertRedirect(route('cashier.walkins.receipt', ['walkin' => $orderId]));

    expect(rtdb()->get("orders/{$orderId}")['change'])->toBe(0.0);
});

it('ignores a stale tile tap on a settled order', function (): void {
    seedProduct('p1', ['price' => 200.0]);

    $orderId = openWalkIn();
    addItem($orderId, 'p1');

    $this->post(route('cashier.walkins.settle', ['walkin' => $orderId]), ['cash' => '200']);

    $this->post(route('cashier.walkins.items.store', ['walkin' => $orderId]), ['product' => 'p1', 'quantity' => 1])
        ->assertRedirect(route('cashier.walkins.show', ['walkin' => $orderId]));

    expect(rtdb()->get("orders/{$orderId}")['total'])->toBe(200.0);
});
