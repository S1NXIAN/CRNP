<?php

declare(strict_types=1);

/**
 * seed.php — idempotent dev seed for staff accounts + catalog.
 *
 * Usage: php seed.php
 * Reads FIREBASE_DATABASE_URL from .env (see .env.example).
 * Seeds staff (1 admin, 3 kitchen, 3 cashiers), 5 pre-verified customers,
 * 10 products with literal photos from assets/img/products, and 10 rentals
 * (no photos in repo, so image stays '' and the UI falls back to placeholder).
 * Missing rows POST in parallel via curl_multi; reruns skip existing.
 *
 * Dev-only defaults; change passwords after first login.
 */

use App\Models\Product;
use App\Models\RentItem;

if (php_sapi_name() !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/init.php';

$db = getDB();
$jobs = [];
$skipped = 0;

$accounts = [
    ['path' => 'admins', 'email' => 'admin@gmail.com', 'password' => 'admin123', 'data' => ['name' => 'Administrator']],
    ['path' => 'kitchen', 'email' => 'cook-1@gmail.com', 'password' => 'cook1123', 'data' => ['full_name' => 'Cook 1']],
    ['path' => 'kitchen', 'email' => 'cook-2@gmail.com', 'password' => 'cook2123', 'data' => ['full_name' => 'Cook 2']],
    ['path' => 'kitchen', 'email' => 'cook-3@gmail.com', 'password' => 'cook3123', 'data' => ['full_name' => 'Cook 3']],
    ['path' => 'cashiers', 'email' => 'pos-1@gmail.com', 'password' => 'pos11234', 'data' => ['name' => 'POS 1']],
    ['path' => 'cashiers', 'email' => 'pos-2@gmail.com', 'password' => 'pos21234', 'data' => ['name' => 'POS 2']],
    ['path' => 'cashiers', 'email' => 'pos-3@gmail.com', 'password' => 'pos31234', 'data' => ['name' => 'POS 3']],
];

/* ---------- Staff (one bulk read per table, not a query per email) ---------- */
$knownEmails = [];
foreach (['admins', 'cashiers', 'kitchen'] as $table) {
    foreach (rows($db->retrieve('/' . $table)) as $row) {
        if (is_array($row) && isset($row['email'])) {
            $knownEmails[strtolower((string) $row['email'])] = true;
        }
    }
}

foreach ($accounts as $account) {
    if (isset($knownEmails[strtolower($account['email'])])) {
        echo 'SKIP  ' . $account['email'] . " (exists)\n";
        $skipped++;
        continue;
    }
    $jobs[] = [
        'table' => $account['path'],
        'label' => $account['email'] . ' -> /' . $account['path'],
        'data' => $account['data'] + [
            'email' => $account['email'],
            'password_hash' => password_hash($account['password'], PASSWORD_BCRYPT),
            'created_at' => now(),
        ],
    ];
    $knownEmails[strtolower($account['email'])] = true;
}

/* ---------- Products (10, literal photos, skip existing names) ---------- */
$knownProducts = [];
foreach (Product::raw() as $row) {
    if (is_array($row) && isset($row['name'])) {
        $knownProducts[strtolower((string) $row['name'])] = true;
    }
}

$products = [
    ['name' => 'Crispy Sisig', 'file' => 'sisig.png', 'category' => 'mains', 'price' => 189.00, 'description' => 'Sizzling chopped pork with calamansi and chili, served on a hot plate.'],
    ['name' => 'Shrimp Sinigang', 'file' => 'shrimp sinigang.jpg', 'category' => 'mains', 'price' => 249.00, 'description' => 'Sour tamarind soup with plump shrimp and fresh vegetables.'],
    ['name' => 'Buttered Garlic Scallops', 'file' => 'scallops.jpg', 'category' => 'starters', 'price' => 229.00, 'description' => 'Pan-seared scallops in butter and garlic, finished with lemon.'],
    ['name' => 'Loaded Nachos', 'file' => 'nachos.jpg', 'category' => 'starters', 'price' => 149.00, 'description' => 'Crispy nachos with cheese sauce, ground beef, salsa, and sour cream.'],
    ['name' => 'Iced Mocha', 'file' => 'mocha.png', 'category' => 'drinks', 'price' => 129.00, 'description' => 'Chilled espresso with chocolate and milk over ice.'],
    ['name' => 'Iced Matcha Latte', 'file' => 'matcha.png', 'category' => 'drinks', 'price' => 135.00, 'description' => 'Whisked matcha with cold milk over ice, lightly sweetened.'],
    ['name' => 'Lumpiang Shanghai', 'file' => 'lumpia.jpg', 'category' => 'starters', 'price' => 159.00, 'description' => 'Dozen crispy pork spring rolls with sweet chili dip.'],
    ['name' => 'Grilled Bangus', 'file' => 'grilled bangus.jpg', 'category' => 'mains', 'price' => 219.00, 'description' => 'Char-grilled milkfish with tomatoes, onions, and calamansi.'],
    ['name' => 'Classic Crate Burger', 'file' => 'burgers.jpg', 'category' => 'mains', 'price' => 165.00, 'description' => 'Beef patty with cheese, lettuce, tomato, and house sauce on a toasted bun.'],
    ['name' => "Jack Daniel's Whiskey", 'file' => 'jack daniels.jpg', 'category' => 'drinks', 'price' => 1450.00, 'description' => 'Tennessee whiskey bottle, served at the counter for of-age guests.'],
];

foreach ($products as $product) {
    if (isset($knownProducts[strtolower($product['name'])])) {
        echo 'SKIP  ' . $product['name'] . " (exists)\n";
        $skipped++;
        continue;
    }
    $image = '';
    $raw = @file_get_contents(__DIR__ . '/assets/img/products/' . $product['file']);
    if (is_string($raw) && $raw !== '') {
        try {
            $image = 'b64:' . base64_encode(upload_normalize_bytes($raw, 'admin/item'));
        } catch (Throwable $ex) {
            echo 'WARN  ' . $product['name'] . ': image skipped (' . $ex->getMessage() . ")\n";
        }
    } else {
        echo 'WARN  ' . $product['name'] . ": photo missing\n";
    }
    $jobs[] = [
        'table' => 'products',
        'label' => $product['name'] . ' -> /products',
        'data' => [
            'name' => $product['name'],
            'description' => $product['description'],
            'price' => $product['price'],
            'category' => $product['category'],
            'image' => $image,
            'status' => 'available',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ];
    $knownProducts[strtolower($product['name'])] = true;
}

/* ---------- Rentals (10, no photos in repo: placeholder fallback) ---------- */
$knownRentals = [];
foreach (RentItem::raw() as $row) {
    if (is_array($row) && isset($row['name'])) {
        $knownRentals[strtolower((string) $row['name'])] = true;
    }
}

$rentals = [
    ['name' => 'monobloc-chair', 'display_name' => 'Monobloc Chair', 'price' => 15.00, 'quantity' => 100, 'description' => 'Sturdy plastic chair for indoor or outdoor events.'],
    ['name' => 'plastic-table', 'display_name' => 'Rectangular Plastic Table', 'price' => 120.00, 'quantity' => 20, 'description' => 'Six-foot folding table, seats up to 6 guests.'],
    ['name' => 'round-banquet-table', 'display_name' => 'Round Banquet Table (10-seater)', 'price' => 250.00, 'quantity' => 10, 'description' => 'Round folding table with seating for 10 guests.'],
    ['name' => 'tent-3x3', 'display_name' => 'Canopy Tent 3x3m', 'price' => 800.00, 'quantity' => 5, 'description' => 'Pop-up canopy tent, rain or shine cover for small gatherings.'],
    ['name' => 'sound-system', 'display_name' => 'Basic Sound System', 'price' => 1500.00, 'quantity' => 2, 'description' => 'Speaker pair with mixer and stands for speeches and music.'],
    ['name' => 'wireless-microphone', 'display_name' => 'Wireless Microphone', 'price' => 300.00, 'quantity' => 4, 'description' => 'Handheld wireless mic with receiver, batteries included.'],
    ['name' => 'projector-screen', 'display_name' => 'Projector with Screen', 'price' => 900.00, 'quantity' => 2, 'description' => 'HD projector plus tripod screen for slideshows and karaoke.'],
    ['name' => 'buffet-warmer', 'display_name' => 'Buffet Food Warmer', 'price' => 200.00, 'quantity' => 8, 'description' => 'Chafing dish with fuel holder, keeps food warm for hours.'],
    ['name' => 'cooler-box', 'display_name' => 'Cooler Box 50L', 'price' => 150.00, 'quantity' => 6, 'description' => 'Insulated 50-liter cooler for drinks and perishables.'],
    ['name' => 'string-lights', 'display_name' => 'String Lights Set (10m)', 'price' => 250.00, 'quantity' => 8, 'description' => 'Warm-white outdoor string lights, 10 meters per set.'],
];

foreach ($rentals as $rental) {
    if (isset($knownRentals[strtolower($rental['name'])])) {
        echo 'SKIP  ' . $rental['name'] . " (exists)\n";
        $skipped++;
        continue;
    }
    $jobs[] = [
        'table' => 'rent_items',
        'label' => $rental['name'] . ' -> /rent_items',
        'data' => [
            'name' => $rental['name'],
            'display_name' => $rental['display_name'],
            'description' => $rental['description'],
            'price' => $rental['price'],
            'quantity' => $rental['quantity'],
            'image' => '',
            'status' => 'available',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ];
    $knownRentals[strtolower($rental['name'])] = true;
}

/* ---------- Customers (5, pre-verified, skip existing emails) ---------- */
$knownUsers = [];
foreach (rows($db->retrieve('/user')) as $row) {
    if (is_array($row) && isset($row['email'])) {
        $knownUsers[strtolower((string) $row['email'])] = true;
    }
}

for ($n = 1; $n <= 5; $n++) {
    $email = 'user' . $n . '@gmail.com';
    if (isset($knownUsers[$email])) {
        echo 'SKIP  ' . $email . " (exists)\n";
        $skipped++;
        continue;
    }
    $jobs[] = [
        'table' => 'user',
        'label' => $email . ' -> /user',
        'data' => [
            'name' => 'User ' . $n,
            'email' => $email,
            'password_hash' => password_hash('user' . $n . '1234', PASSWORD_BCRYPT),
            'email_verified' => true,
            'created_at' => now(),
            'profile_image' => '',
            'provider' => 'email',
        ],
    ];
    $knownUsers[$email] = true;
}

/* ---------- Fire all missing rows in parallel ---------- */
$created = 0;
$failed = 0;
$mh = curl_multi_init();
$handles = [];
foreach ($jobs as $i => $job) {
    $ch = curl_init($db->authedUrl('/' . $job['table']));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($job['data'], JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT => 120,
    ]);
    curl_multi_add_handle($mh, $ch);
    $handles[$i] = $ch;
}
if ($handles !== []) {
    $active = 0;
    do {
        $status = curl_multi_exec($mh, $active);
        if ($active) {
            curl_multi_select($mh);
        }
    } while ($active && $status === CURLM_OK);
}
foreach ($handles as $i => $ch) {
    $label = $jobs[$i]['label'];
    if (curl_errno($ch)) {
        echo 'FAIL  ' . $label . ' (' . curl_error($ch) . ")\n";
        $failed++;
    } else {
        $arr = json_decode((string) curl_multi_getcontent($ch), true);
        if (is_array($arr) && isset($arr['error'])) {
            $msg = is_string($arr['error']) ? $arr['error'] : json_encode($arr['error']);
            echo 'FAIL  ' . $label . ' (' . $msg . ")\n";
            $failed++;
        } else {
            echo 'SEED  ' . $label . "\n";
            $created++;
        }
    }
    curl_multi_remove_handle($mh, $ch);
}
curl_multi_close($mh);

Product::clearRawCache();
RentItem::clearRawCache();

echo "Done: {$created} created, {$skipped} skipped, {$failed} failed.\n";
exit($failed > 0 ? 1 : 0);
