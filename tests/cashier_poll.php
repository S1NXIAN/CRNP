<?php

/**
 * cashier_poll.php — offline checks for Order::selectNew(), the pure seam
 * behind the POS live-row poll (?check=1&known=...).
 *
 * Run: php tests/cashier_poll.php (no network, no credentials).
 */
declare(strict_types=1);

require __DIR__ . '/../app/Core/Model.php';
require __DIR__ . '/../app/Models/Order.php';

use App\Models\Order;

require_once __DIR__ . '/helpers.php';

$pending = [
    'aaa' => ['status' => 'pending', 'created_at' => '2026-09-07 10:00:00', 'total' => 100],
    'bbb' => ['status' => 'pending', 'created_at' => '2026-09-07 10:05:00', 'total' => 200],
    'ccc' => ['status' => 'pending', 'created_at' => '2026-09-07 10:03:00', 'total' => 300],
];

$new = Order::selectNew($pending, ['aaa']);
check('excludes known ids', array_keys($new) === ['bbb', 'ccc']);
check('newest first', array_keys(Order::selectNew($pending, [])) === ['bbb', 'ccc', 'aaa']);
check('empty known returns all, capped', count(Order::selectNew($pending, [], 2)) === 2);
check('all known returns none', Order::selectNew($pending, ['aaa', 'bbb', 'ccc']) === []);
check('non-array rows skipped', Order::selectNew(['zzz' => null] + $pending, ['aaa', 'bbb', 'ccc']) === []);
check('missing dates sink last', array_keys(Order::selectNew(['nodate' => ['status' => 'pending']] + $pending, []))[3] === 'nodate');

exit($fails === 0 ? 0 : 1);
