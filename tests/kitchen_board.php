<?php

/**
 * kitchen_board.php — offline checks for Order::kitchenBoard(), the pure seam
 * behind the two-column kitchen screen (accepted | cooking).
 *
 * Run: php tests/kitchen_board.php (no network, no credentials).
 */
declare(strict_types=1);

require __DIR__ . '/../app/Core/Model.php';
require __DIR__ . '/../app/Models/Order.php';

use App\Models\Order;

require_once __DIR__ . '/helpers.php';

$orders = [
    'new-acc' => ['status' => 'accepted', 'created_at' => '2026-09-07 10:05:00'],
    'old-acc' => ['status' => 'accepted', 'created_at' => '2026-09-07 10:00:00'],
    'prep' => ['status' => 'preparing', 'created_at' => '2026-09-07 10:02:00'],
    'ready' => ['status' => 'ready', 'created_at' => '2026-09-07 10:01:00'],
    'pend' => ['status' => 'pending', 'created_at' => '2026-09-07 09:00:00'],
    'done' => ['status' => 'done', 'created_at' => '2026-09-07 08:00:00'],
    'cancelled' => ['status' => 'cancelled', 'created_at' => '2026-09-07 08:30:00'],
    'cashier_cancelled' => ['status' => 'cashier_cancelled', 'created_at' => '2026-09-07 08:45:00'],
    'nodate' => ['status' => 'accepted'],
];

$board = Order::kitchenBoard($orders);

check('splits accepted', array_keys($board['accepted']) === ['old-acc', 'new-acc', 'nodate']);
check('cooking merges preparing + ready', array_keys($board['cooking']) === ['ready', 'prep']);
check('hides pending/done/cancelled', !isset($board['accepted']['pend']) && !isset($board['cooking']['done']));
check('oldest first', array_keys(Order::kitchenBoard($orders)['accepted'])[0] === 'old-acc');
check('missing dates sink last', array_keys($board['accepted'])[2] === 'nodate');
check('non-array rows skipped', Order::kitchenBoard(['zzz' => null] + $orders)['accepted'] === $board['accepted']);
check('empty in empty out', Order::kitchenBoard([]) === ['accepted' => [], 'cooking' => []]);

exit($fails === 0 ? 0 : 1);
