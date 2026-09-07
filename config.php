<?php

/**
 * config.php — global configuration for CRATES N' PLATES.
 * Starts the session (with hardened cookie settings), sets timezone,
 * and defines credentials.
 *
 * Env vars (see .env.example — one line each):
 *   FIREBASE_DATABASE_URL, FIREBASE_SERVICE_ACCOUNT_JSON,
 *   GMAIL_ADDRESS, GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET,
 *   GMAIL_REFRESH_TOKEN, MAIL_FROM (optional), DEV_SHOW_OTP
 */
// ---------- .env loader (written by setup.sh) ----------
$envFile = __DIR__ . '/.env';
if (is_readable($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if (str_contains($line, '=')) {
            [$key, $val] = explode('=', $line, 2);
            $key = trim($key);
            $val = trim($val, " \t\n\r\0\x0B\"'");
            if ($key !== '') {
                putenv("$key=$val");
            }
        }
    }
}

// ---------- Session cookie hardening (C1) ----------
// These MUST run before session_start() so the cookie sent to the browser
// picks up the hardened flags.
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_strict_mode', 1);
ini_set('session.use_only_cookies', 1);
// cookie_secure: only when HTTPS detected (so dev on http:// still works)
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    ini_set('session.cookie_secure', 1);
}

// Give each role its own session cookie so they can be open simultaneously.
$uri = $_SERVER['REQUEST_URI'] ?? '/';
if (preg_match('#/admin(?:/|$)#', $uri)) {
    session_name('SESS_ADMIN');
} elseif (preg_match('#/cashier(?:/|$)#', $uri)) {
    session_name('SESS_CASHIER');
} elseif (preg_match('#/kitchen(?:/|$)#', $uri)) {
    session_name('SESS_KITCHEN');
} else {
    session_name('SESS_USER');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* Idle session timeout — 24h since last activity destroys the session. */
$inactive = 86400;
if (!empty($_SESSION) && isset($_SESSION['_last_activity']) && time() - (int) $_SESSION['_last_activity'] > $inactive) {
    $_SESSION = [];
}
$_SESSION['_last_activity'] = time();

date_default_timezone_set('Asia/Manila');

// ---------- Firebase Realtime Database ----------
$databaseURL = getenv('FIREBASE_DATABASE_URL') ?: 'https://YOUR-PROJECT-default-rtdb.firebaseio.com';

// ---------- Gmail identity (Gmail API sends as the consented account) ----------
define('GMAIL_ADDRESS', getenv('GMAIL_ADDRESS') ?: 'your.app@gmail.com');
define('MAIL_FROM', getenv('MAIL_FROM') ?: GMAIL_ADDRESS);
define('MAIL_FROM_NAME', 'CRATES N\' PLATES');

// ---------- Brand ----------
define('BRAND_NAME', 'CRATES N\' PLATES');
define('BRAND_TAGLINE', 'Diner');

// ---------- Dev OTP fallback ----------
// DEV_SHOW_OTP=1 prints the OTP on screen when mail fails. Local dev only.
define('DEV_SHOW_OTP', getenv('DEV_SHOW_OTP') === '1' || getenv('DEV_SHOW_OTP') === 'true');

// ---------- Uploads ----------
// Uploads live under php-app/uploads/ and are served from /uploads/...
define('UPLOAD_ROOT', __DIR__ . '/uploads');
define('UPLOAD_WEB', '/uploads');

// ---------- Dev error reporting (turn off display_errors in production) ----------
ini_set('display_errors', '1');
error_reporting(E_ALL);
