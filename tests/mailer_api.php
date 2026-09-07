<?php

/**
 * mailer_api.php — offline checks for the Gmail API transport seams:
 * base64url encoding, MIME assembly, and the credential gate.
 *
 * Run: php tests/mailer_api.php (no network, no credentials).
 */
declare(strict_types=1);

if (!defined('MAIL_FROM')) {
    define('MAIL_FROM', 'shop@example.com');
}
if (!defined('MAIL_FROM_NAME')) {
    define('MAIL_FROM_NAME', 'TEST SHOP');
}
if (!defined('BRAND_NAME')) {
    define('BRAND_NAME', 'TEST');
}

require __DIR__ . '/../mailer.php';
require_once __DIR__ . '/helpers.php';

$raw = random_bytes(64);
$enc = gmail_b64url($raw);
check('b64url alphabet', preg_match('/^[A-Za-z0-9\\-_]*$/', $enc) === 1);
check('b64url round-trip', base64_decode(strtr($enc, '-_', '+/')) === $raw);

check('crlf address refused without dns', is_deliverable("a@b.com\r\nBcc: x@y.z") === false);

$mime = gmail_mime('a@b.com', 'Café order №7', '<p>hi</p>', 'hi');
check('mime to + version', str_contains($mime, 'To: <a@b.com>') && str_contains($mime, 'MIME-Version: 1.0'));
check('mime subject encoded', (bool) preg_match('/^Subject: =\?UTF-8\?B\?[A-Za-z0-9+\/=]+\?=\r?$/m', $mime));
check('mime carries both parts', str_contains($mime, '<p>hi</p>') && str_contains($mime, "Content-Type: text/plain; charset=UTF-8\r\n"));
check('mime boundary closes', preg_match('/boundary="([^"]+)"/', $mime, $m) === 1 && substr_count($mime, $m[1]) === 4);
check('raw round-trip', base64_decode(strtr(gmail_b64url($mime), '-_', '+/')) === $mime);

foreach (['GOOGLE_CLIENT_ID', 'GOOGLE_CLIENT_SECRET', 'GMAIL_REFRESH_TOKEN'] as $key) {
    putenv($key);
}
check('gate closed without creds', gmail_api_ready() === false);
putenv('GOOGLE_CLIENT_ID=x');
check('gate closed on partial creds', gmail_api_ready() === false);
putenv('GOOGLE_CLIENT_SECRET=y');
putenv('GMAIL_REFRESH_TOKEN=z');
check('gate open with all creds', gmail_api_ready() === true);
foreach (['GOOGLE_CLIENT_ID', 'GOOGLE_CLIENT_SECRET', 'GMAIL_REFRESH_TOKEN'] as $key) {
    putenv($key);
}

exit($fails === 0 ? 0 : 1);
