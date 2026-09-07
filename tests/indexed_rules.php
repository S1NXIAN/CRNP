<?php

/**
 * indexed_rules.php — offline checks for indexed (server-side) Firebase queries.
 *
 * Covers the two seams the indexed migration depends on:
 *   1. database.rules.json is valid and indexes every field the code
 *      queries with orderBy (see INDEX_COVERAGE below).
 *   2. firebaseRDB builds correct REST query URLs (orderBy/equalTo,
 *      prefix LIKE ranges, ordered ranges, limits) and drops
 *      {"error": ...} GET bodies (e.g. "Index not defined") instead of
 *      returning them as rows.
 *
 * Run: php tests/indexed_rules.php (no network, no credentials).
 */
declare(strict_types=1);

require __DIR__ . '/../firebaseRDB.php';

$fails = 0;
function check(string $name, bool $ok): void
{
    global $fails;
    echo ($ok ? 'PASS' : 'FAIL') . "  $name\n";
    if (!$ok) {
        $fails++;
    }
}

/* ---------- 1. rules file ---------- */
$rulesPath = __DIR__ . '/../database.rules.json';
check('database.rules.json exists', is_file($rulesPath));
$rules = is_file($rulesPath) ? json_decode((string) file_get_contents($rulesPath), true) : null;
check('database.rules.json is valid JSON', is_array($rules));

// Auth posture must stay locked down (see README maintenance notes).
check('rules keep auth != null reads', ($rules['rules']['.read'] ?? null) === 'auth != null');
check('rules keep auth != null writes', ($rules['rules']['.write'] ?? null) === 'auth != null');

// Every field queried server-side must have a matching .indexOn entry.
// ponytail: extend this map when a new where() field is added.
const INDEX_COVERAGE = [
    'user'      => ['email'],
    'admins'    => ['email'],
    'cashiers'  => ['email'],
    'kitchen'   => ['email'],
    'orders'    => ['user_email', 'status', 'created_at', 'payment_status'],
    'bookings'  => ['user_email', 'status', 'created_at', 'payment_status'],
    'products'  => ['name', 'status', 'category'],
    'rent_items' => ['name', 'status'],
];
foreach (INDEX_COVERAGE as $node => $fields) {
    $indexed = (array) ($rules['rules'][$node]['.indexOn'] ?? []);
    foreach ($fields as $field) {
        check("rules index {$node}/{$field}", in_array($field, $indexed, true));
    }
}

/* ---------- 2. query URL builder ---------- */
putenv('FIREBASE_SERVICE_ACCOUNT_JSON'); // force unauthenticated: no access_token in URLs.
$db = new firebaseRDB('https://example-default-rtdb.firebaseio.com');

check(
    'equality query',
    $db->buildQueryUrl('/orders', 'status', firebaseRDB::EQUAL, 'pending')
        === 'https://example-default-rtdb.firebaseio.com/orders.json?orderBy="status"&equalTo="pending"'
);
check(
    'prefix LIKE query',
    $db->buildQueryUrl('/products', 'name', firebaseRDB::LIKE, 'Adobo')
        === 'https://example-default-rtdb.firebaseio.com/products.json?orderBy="name"&startAt="Adobo"&endAt="Adobo\uf8ff"'
);
check(
    'ordered range query',
    $db->buildQueryUrl('/orders', 'created_at', null, null, ['startAt' => '2026-09-01', 'endAt' => '2026-09-30'])
        === 'https://example-default-rtdb.firebaseio.com/orders.json?orderBy="created_at"&startAt="2026-09-01"&endAt="2026-09-30"'
);
check(
    'ordered limit query',
    $db->buildQueryUrl('/orders', 'created_at', null, null, ['limitToLast' => 8])
        === 'https://example-default-rtdb.firebaseio.com/orders.json?orderBy="created_at"&limitToLast=8'
);
check(
    'plain path has no query string',
    $db->buildQueryUrl('/settings') === 'https://example-default-rtdb.firebaseio.com/settings.json'
);

/* ---------- 3. GET response parsing ---------- */
check('rows pass through', $db->parseGetResponse('{"a":{"email":"x@y.z"}}') === ['a' => ['email' => 'x@y.z']]);
check('null body means empty', $db->parseGetResponse('null') === []);
check(
    'index error body means empty, not rows',
    $db->parseGetResponse('{"error":"Index not defined, add \\".indexOn\\": \\"status\\"}"') === []
);

exit($fails === 0 ? 0 : 1);
