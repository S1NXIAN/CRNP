<?php

/**
 * functions.php — shared helpers used across every role.
 */

/* ---------- security headers (C2) ---------- */
/**
 * Send a baseline set of security headers. Call as early as possible
 * (before any HTML output) on every page, including standalone auth pages.
 */
function security_headers(): void
{
    if (headers_sent()) {
        return;
    }
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data: blob: https:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; script-src 'self' 'unsafe-inline'; frame-src 'self' https://www.google.com https://maps.google.com; connect-src 'self'; object-src 'none'; base-uri 'self'");
}

/* ---------- CSRF protection (C3) ---------- */
/**
 * Get (or lazily create) the per-session CSRF token.
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
/**
 * Render a hidden <input> containing the CSRF token. Drop inside every POST form.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES) . '">';
}
/**
 * Verify the CSRF token submitted with a POST request. Bails on mismatch.
 * Call at the very top of every POST handler block.
 */
function csrf_verify(): void
{
    $t = $_POST['csrf_token'] ?? '';
    if (empty($t) || !hash_equals($_SESSION['csrf_token'] ?? '', $t)) {
        http_response_code(419);
        flash('Security token expired. Please try again.', 'danger');
        redirect($_SERVER['HTTP_REFERER'] ?? '/');
    }
}

/* ---------- rate limiting (C5) ----------
 * Simple file-backed sliding-window limiter. Returns true when the action
 * is allowed (and records the attempt), false when the limit is exceeded.
 * The bucket file lives in sys_get_temp_dir() and is keyed by an opaque
 * string (e.g. 'login_' . $email). */
function rate_limit(string $key, int $maxAttempts, int $windowSecs): bool
{
    $file = sys_get_temp_dir() . '/rl_' . md5($key) . '.json';
    $now  = time();
    $data = [];
    if (is_file($file)) {
        $data = json_decode((string) file_get_contents($file), true) ?: [];
    }
    // purge attempts outside the window
    $data = array_values(array_filter($data, fn ($t) => $t > $now - $windowSecs));
    if (count($data) >= $maxAttempts) {
        return false; // limit exceeded
    }
    $data[] = $now;
    file_put_contents($file, json_encode($data), LOCK_EX);
    return true;
}

/* ---------- output / flow ---------- */
function e($s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}
function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}
function redirect_background(string $url, callable $job): void
{
    ignore_user_abort(true);
    session_write_close();
    header('Location: ' . $url);
    header('Connection: close');
    header('Content-Length: 0');
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    flush();
    $job();
    exit;
}
function flash_after_redirect(string $message, string $type = 'info'): void
{
    $sid = $_COOKIE[session_name()] ?? '';
    if (!is_string($sid) || $sid === '') {
        return;
    }
    ini_set('session.cache_limiter', '');
    ini_set('session.use_cookies', '0');
    session_id($sid);
    session_start();
    flash($message, $type);
    session_write_close();
}
/**
 * Send an OTP after the redirect, surfacing the result on the destination
 * page. Centralises the DEV_SHOW_OTP gate so the call sites stay one line
 * and the policy lives in one place.
 */
function otp_background(string $url, string $email, string $otp, string $purpose, string $failureFlash): void
{
    redirect_background($url, function () use ($email, $otp, $purpose, $failureFlash) {
        if (sendOTP($email, $otp, $purpose)) {
            return;
        }
        $dev = defined('DEV_SHOW_OTP') && DEV_SHOW_OTP;
        flash_after_redirect(
            $dev ? 'SMTP not configured — OTP is ' . $otp . ' (dev only).' : $failureFlash,
            $dev ? 'warn' : 'danger'
        );
    });
}
function now(): string
{
    return date('Y-m-d H:i:s');
}
function money($n): string
{
    return "\u{20B1}" . number_format((float) $n, 2); // ₱
}
function gen_otp(): string
{
    try {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    } catch (Throwable $e) {
        return str_pad((string) mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}

/**
 * Seconds until a new OTP may issue for this record; 0 when no live code.
 * Same clock as expiry: resend opens exactly when the code dies, so two
 * codes never overlap. $expField is 'otp_expires' (signup) or
 * 'reset_otp_expires' (password reset).
 */
function otp_resend_wait(?array $user, string $expField): int
{
    if (!is_array($user) || empty($user[$expField])) {
        return 0;
    }
    $exp = strtotime((string) $user[$expField]);
    return $exp === false ? 0 : max(0, $exp - time());
}

/**
 * Enforce the resend cooldown: while a live code exists, flash the wait and
 * redirect back. Falls through silently once resend is open.
 */
function otp_resend_gate(mixed $user, string $expField, string $back): void
{
    $wait = otp_resend_wait(is_array($user) ? $user : null, $expField);
    if ($wait > 0) {
        flash('A code is already on its way — resend opens in ' . gmdate('i:s', $wait) . '.', 'warn');
        redirect($back);
    }
}
function rows($data): array
{
    return is_array($data) ? $data : [];
}

/* ---------- flash messages ---------- */
function flash(string $message, string $type = 'info'): void
{
    if (!isset($_SESSION['flash'])) {
        $_SESSION['flash'] = [];
    }
    $_SESSION['flash'][] = ['message' => $message, 'type' => $type];
}
function get_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/* ---------- Firebase filtering (PHP-side; avoids indexOn rules) ---------- */
function filter_by(array $rows, string $key, $val): array
{
    $out = [];
    foreach ($rows as $id => $row) {
        if (is_array($row) && array_key_exists($key, $row) && strcasecmp((string) $row[$key], (string) $val) === 0) {
            $out[$id] = $row;
        }
    }
    return $out;
}
function filter_like(array $rows, string $key, $val): array
{
    $out = [];
    $v = strtolower((string) $val);
    foreach ($rows as $id => $row) {
        if (is_array($row) && array_key_exists($key, $row) && strpos(strtolower((string) $row[$key]), $v) !== false) {
            $out[$id] = $row;
        }
    }
    return $out;
}

/* ---------- indexed lookups (server-side; need database.rules.json) ----------
 * Case-insensitive email match over an indexed equality query. Firebase
 * equalTo is case-sensitive while filter_by is not, so refine in PHP and
 * fall back to a full scan only on a miss (differently-cased stored email).
 * Auth pages are rate-limited, so the rare fallback stays cheap. */
function db_find_by_email(string $table, string $email): array
{
    $path = '/' . ltrim($table, '/');
    $match = filter_by(rows(getDB()->retrieve($path, 'email', firebaseRDB::EQUAL, $email)), 'email', $email);
    if ($match !== []) {
        return $match;
    }
    return filter_by(rows(getDB()->retrieve($path)), 'email', $email);
}

/* ---------- upload pipeline (single seam) ----------
 * Every photo intake routes through upload_normalize_bytes(): validate real
 * image type, bound dimensions, compress to JPEG, strip metadata. Identity is
 * content-addressed (hash of normalized bytes) so retries converge.
 * File categories (avatar, settings) persist one file per distinct image;
 * b64 categories (menu, rent, receipt) persist one b64 value with no local
 * copy. Callers delete the replaced reference only after the DB write wins.
 */
/** @return array{dim:int,bytes:int,kind:string} */
function upload_preset(string $category): array
{
    $cat = trim($category, '/');
    if ($cat === 'user/profile') {
        return ['dim' => 384, 'bytes' => 100 * 1024, 'kind' => 'file'];
    }
    if ($cat === 'settings') {
        return ['dim' => 512, 'bytes' => 120 * 1024, 'kind' => 'file'];
    }
    if ($cat === 'user/bookings') {
        return ['dim' => 1024, 'bytes' => 300 * 1024, 'kind' => 'b64'];
    }
    return ['dim' => 1280, 'bytes' => 400 * 1024, 'kind' => 'b64'];
}
function upload_category_for_dir(string $dir): string
{
    if (str_contains($dir, 'user/profile')) {
        return 'user/profile';
    }
    if (str_contains($dir, 'settings')) {
        return 'settings';
    }
    if (str_contains($dir, 'user/bookings')) {
        return 'user/bookings';
    }
    return 'admin/item';
}
function upload_normalize_bytes(string $raw, string $category): string
{
    $preset = upload_preset($category);
    $maxDim = (int) $preset['dim'];
    $maxBytes = (int) $preset['bytes'];
    $info = @getimagesizefromstring($raw);
    if ($info === false) {
        throw new Exception('File is not a valid image.');
    }
    $mime = (string) $info['mime'];
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
        throw new Exception('Invalid file type. Only images are allowed.');
    }
    if (function_exists('imagecreatefromstring') && function_exists('imagejpeg')) {
        $src = @imagecreatefromstring($raw);
        if ($src === false) {
            throw new Exception('File is not a valid image.');
        }
        $w = imagesx($src);
        $h = imagesy($src);
        // ponytail: EXIF orientation ignored (no ext-exif); phone shots already upright via canvas crop, fix with exif if misrotated reports arrive.
        if (max($w, $h) > $maxDim) {
            $scale = $maxDim / max($w, $h);
            $nw = max(1, (int) round($w * $scale));
            $nh = max(1, (int) round($h * $scale));
            $scaled = @imagescale($src, $nw, $nh, IMG_BILINEAR_FIXED);
            if ($scaled !== false) {
                $src = $scaled;
                $w = $nw;
                $h = $nh;
            }
        }
        $flat = imagecreatetruecolor($w, $h);
        if ($flat === false) {
            throw new Exception('Failed to process image.');
        }
        $white = imagecolorallocate($flat, 255, 255, 255);
        imagefill($flat, 0, 0, $white === false ? 0 : $white);
        imagecopy($flat, $src, 0, 0, 0, 0, $w, $h);
        $best = null;
        foreach ([82, 72, 65, 60] as $q) {
            ob_start();
            imagejpeg($flat, null, $q);
            $out = (string) ob_get_clean();
            if ($out !== '') {
                $best = $out;
            }
            if ($out !== '' && strlen($out) <= $maxBytes) {
                return $out;
            }
        }
        // ponytail: quality floor 60; dimension bound keeps worst case small, per-category retune if budgets miss.
        if ($best !== null && $best !== '') {
            return $best;
        }
        throw new Exception('Failed to process image.');
    }
    throw new Exception('Failed to process image.');
}
function upload_normalize_upload(string $field, string $category, int $maxMB = 5): ?string
{
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Upload error (code ' . $_FILES[$field]['error'] . ').');
    }
    if ($_FILES[$field]['size'] > $maxMB * 1024 * 1024) {
        throw new Exception('File exceeds ' . $maxMB . 'MB limit.');
    }
    $tmp = (string) $_FILES[$field]['tmp_name'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo !== false ? (string) finfo_file($finfo, $tmp) : '';
    if ($finfo !== false) {
        finfo_close($finfo);
    }
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
        throw new Exception('Invalid file type. Only images are allowed.');
    }
    $raw = @file_get_contents($tmp);
    if ($raw === false || $raw === '') {
        throw new Exception('Failed to read uploaded file.');
    }
    return upload_normalize_bytes($raw, $category);
}
/**
 * Save an uploaded file through the pipeline. Returns the content-addressed
 * filename or null if no file. Reuses the stored file on retry.
 * Delete the replaced filename only after the DB write succeeds.
 * @throws Exception on validation / IO failure.
 */
function save_upload(string $field, string $destDir, int $maxMB = 5): ?string
{
    $category = upload_category_for_dir($destDir);
    $norm = upload_normalize_upload($field, $category, $maxMB);
    if ($norm === null) {
        return null;
    }
    $name = hash('sha256', $norm) . '.jpg';
    $dir = rtrim($destDir, '/');
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $target = $dir . '/' . $name;
    if (!is_file($target)) {
        $tmpFile = $target . '.tmp' . bin2hex(random_bytes(4));
        if (@file_put_contents($tmpFile, $norm, LOCK_EX) === false) {
            throw new Exception('Failed to save uploaded file.');
        }
        @rename($tmpFile, $target);
    }
    return $name;
}
/** Delete a replaced file reference; no-op when empty or identical. */
function upload_retire_file(string $category, ?string $old, ?string $next): void
{
    if ($old === null || $old === '' || $next === null) {
        return;
    }
    if ($old === $next) {
        return;
    }
    if (preg_match('#^https?://#i', $old) === 1 || str_starts_with($old, 'b64:')) {
        return;
    }
    $cat = trim($category, '/');
    $path = rtrim(UPLOAD_ROOT, '/') . '/' . $cat . '/' . basename($old);
    if (is_file($path)) {
        @unlink($path);
    }
}
/** Web URL for an uploaded asset given a category subpath and filename. */
function upload_web(string $category, ?string $filename): string
{
    if (!$filename) {
        return '/assets/img/placeholder.svg';
    }
    /* External URLs (e.g. Google profile pictures) — return as-is. */
    if (preg_match('#^https?://#i', $filename)) {
        return $filename;
    }
    /* base64 data URIs (Firebase dual-save) — return as-is. */
    if (str_starts_with($filename, 'b64:')) {
        return $filename;
    }
    if ($category === 'admin/item') {
        return '/assets/img/products/' . rawurlencode($filename);
    }
    return UPLOAD_WEB . '/' . $category . '/' . rawurlencode($filename);
}
/* ---------- base64 image storage (Firebase, single authoritative store) ----------
 * Returns a "b64:<base64data>" string of normalized bytes. No local copy is
 * kept; the product proxy holds the only disk cache. Identical retries yield
 * byte-identical values so failed-then-retried writes never duplicate.
 * @throws Exception on validation / IO failure.
 */
function upload_to_base64(string $field, string $localDir = '', int $maxMB = 5): ?string
{
    $category = $localDir !== '' ? upload_category_for_dir($localDir) : 'user/bookings';
    $norm = upload_normalize_upload($field, $category, $maxMB);
    if ($norm === null) {
        return null;
    }
    return 'b64:' . base64_encode($norm);
}
/**
 * Normalize a client-side canvas crop data URL through the same pipeline.
 * Consumes the existing crop input as-is; no uploader redesign.
 */
function upload_cropped_to_base64(mixed $cropped, string $category = 'admin/item'): ?string
{
    if (!is_string($cropped) || $cropped === '') {
        return null;
    }
    $parts = explode(',', $cropped, 2);
    $raw = base64_decode($parts[1] ?? $parts[0] ?? '', true);
    if ($raw === false || strlen($raw) === 0) {
        return null;
    }
    return 'b64:' . base64_encode(upload_normalize_bytes($raw, $category));
}

function image_mime(string $raw): string
{
    if (str_starts_with($raw, "\x89PNG\r\n\x1a\n")) {
        return 'image/png';
    }
    if (str_starts_with($raw, 'RIFF') && substr($raw, 8, 4) === 'WEBP') {
        return 'image/webp';
    }
    if (str_starts_with($raw, 'GIF87a') || str_starts_with($raw, 'GIF89a')) {
        return 'image/gif';
    }
    return 'image/jpeg';
}

/**
 * Return an <img>-ready src attribute from a stored image value.
 * Handles both legacy filenames and new "b64:..." base64 strings.
 * Detects the correct MIME type from the raw bytes for b64: values.
 */
function image_display_src(?string $image, string $legacyDir = 'admin/item'): string
{
    if (!$image || $image === '') {
        return '/assets/img/placeholder.svg';
    }
    if (strncmp($image, 'b64:', 4) === 0) {
        $raw = base64_decode(substr($image, 4), true);
        if ($raw === false || strlen($raw) < 8) {
            return '/assets/img/placeholder.svg';
        }
        $mime = image_mime($raw);
        return 'data:' . $mime . ';base64,' . substr($image, 4);
    }
    return upload_web($legacyDir, $image);
}

/**
 * Return a proxy URL for product/rent-item images stored as b64: in Firebase.
 * Falls back to image_display_src() for legacy filenames or external URLs.
 * Keeps the HTML response small — the browser fetches the image separately.
 */
function product_image_url(?string $image, string $id, string $table = 'products'): string
{
    if (!$image || $image === '') {
        return '/assets/img/placeholder.svg';
    }
    if (str_starts_with($image, 'b64:')) {
        return '/user/product_image.php?id=' . rawurlencode($id) . '&table=' . rawurlencode($table);
    }
    return image_display_src($image);
}
/* ---------- product proxy cache discipline ---------- */
function product_image_cache_path(string $id, string $table): string
{
    $safeTable = $table === 'rent_items' ? 'rent_items' : 'products';
    return rtrim(UPLOAD_ROOT, '/') . '/cache/' . $safeTable . '/' . $id . '.img';
}
function product_image_cache_invalidate(string $id, string $table): void
{
    if (!preg_match('/^[a-zA-Z0-9_\\-]+$/', $id)) {
        return;
    }
    $base = product_image_cache_path($id, $table);
    @unlink($base);
    @unlink($base . '.meta');
}
function product_image_cache_prune(string $table = '', int $maxFiles = 500, int $maxBytes = 100 * 1024 * 1024): void
{
    $roots = [];
    if ($table !== '') {
        $roots[] = rtrim(UPLOAD_ROOT, '/') . '/cache/' . ($table === 'rent_items' ? 'rent_items' : 'products');
    } else {
        $roots[] = rtrim(UPLOAD_ROOT, '/') . '/cache/products';
        $roots[] = rtrim(UPLOAD_ROOT, '/') . '/cache/rent_items';
    }
    foreach ($roots as $dir) {
        if (!is_dir($dir)) {
            continue;
        }
        $files = [];
        $total = 0;
        foreach ((array) glob($dir . '/*.img') as $file) {
            if (!is_file($file)) {
                continue;
            }
            $size = (int) filesize($file);
            $total += $size;
            $files[] = ['path' => $file, 'mtime' => (int) filemtime($file), 'size' => $size];
        }
        if (count($files) <= $maxFiles && $total <= $maxBytes) {
            continue;
        }
        usort($files, static fn (array $a, array $b): int => $a['mtime'] <=> $b['mtime']);
        foreach ($files as $entry) {
            if (count($files) <= $maxFiles && $total <= $maxBytes) {
                break;
            }
            @unlink($entry['path']);
            @unlink($entry['path'] . '.meta');
            $total -= $entry['size'];
            array_shift($files);
        }
    }
}

/* ---------- session cart ---------- */
function items_html($items): string
{
    if (!is_array($items) || empty($items)) {
        return '<span class="muted">No items</span>';
    }
    $parts = [];
    foreach ($items as $it) {
        if (!is_array($it)) {
            continue;
        }
        $name = (string) ($it['name'] ?? 'Item');
        $qty  = (int) ($it['qty'] ?? $it['quantity'] ?? 1);
        $parts[] = e($name) . ' <span class="muted">&times;' . $qty . '</span>';
    }
    return $parts ? implode(', ', $parts) : '<span class="muted">No items</span>';
}

/* ---------- shared display helpers ---------- */
/** Total qty across an order/booking items map. */
function items_count(array $row): int
{
    $n = 0;
    foreach (($row['items'] ?? []) as $info) {
        if (is_array($info)) {
            $n += (int) ($info['qty'] ?? 0);
        }
    }
    return $n;
}
/** Short display id (first 8 chars of a Firebase push key). */
function short_id(string $id): string
{
    return substr($id, 0, 8);
}
/** Customer display name with Guest fallback. */
function order_customer_name(array $order): string
{
    $n = $order['customer_name'] ?? $order['user_name'] ?? $order['name'] ?? '';
    return trim((string) $n) !== '' ? (string) $n : 'Guest';
}
function get_cart(): array
{
    return $_SESSION['cart'] ?? [];
}
function set_cart(array $cart): void
{
    $_SESSION['cart'] = $cart;
}
function cart_count(): int
{
    $n = 0;
    foreach (get_cart() as $item) {
        $n += (int) ($item['qty'] ?? 0);
    }
    return $n;
}
function cart_total(): float
{
    $t = 0.0;
    foreach (get_cart() as $item) {
        $t += (float) ($item['price'] ?? 0) * (int) ($item['qty'] ?? 0);
    }
    return $t;
}

/* ---------- status helpers ---------- */
function order_status_label(string $status): array
{
    $map = [
        'pending'            => ['Pending',     'badge--warn'],
        'accepted'           => ['Accepted',    'badge--info'],
        'preparing'          => ['Preparing',   'badge--info'],
        'ready'              => ['Ready',       'badge--gold'],
        'done'               => ['Completed',   'badge--ok'],
        'cancelled'          => ['Cancelled',   'badge--muted'],
        'cashier_cancelled'  => ['Cancelled',   'badge--muted'],
    ];
    return $map[$status] ?? [ucfirst($status), 'badge--muted'];
}
function booking_status_label(string $status): array
{
    $map = [
        'pending'   => ['Pending',   'badge--warn'],
        'accepted'  => ['Approved',  'badge--info'],
        'rejected'  => ['Rejected',  'badge--muted'],
        'returned'  => ['Returned',  'badge--ok'],
        'cancelled' => ['Cancelled', 'badge--muted'],
    ];
    return $map[$status] ?? [ucfirst($status), 'badge--muted'];
}
function payment_status_label(string $status): array
{
    $map = [
        'pending_verification'  => ['Verifying',  'badge--warn'],
        'paid'                  => ['Paid',       'badge--ok'],
        'unpaid'                => ['Unpaid',     'badge--danger'],
        'no_payment_required'   => ['At counter', 'badge--muted'],
    ];
    return $map[$status] ?? [ucfirst($status), 'badge--muted'];
}
function payment_method_label(string $method): array
{
    $map = [
        'gcash'   => ['GCash',   'badge--blue'],
        'counter' => ['Counter', 'badge--muted'],
    ];
    return $map[$method] ?? [ucfirst($method), 'badge--muted'];
}

/* ---------- input ---------- */
function post(string $key, $default = '')
{
    return isset($_POST[$key]) ? $_POST[$key] : $default;
}

function is_ajax_request(): bool
{
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/* ---------- cross-request file cache (P0) ----------
 * Persists data across requests with a TTL. Useful for slow Firebase reads
 * that are acceptable to serve slightly stale (settings, dashboard charts).
 * Stored in sys_get_temp_dir() to avoid permission issues. */
function cache_file_get(string $key, int $ttl, callable $loader)
{
    $dir = sys_get_temp_dir() . '/crnp_cache';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $file = $dir . '/' . md5($key) . '.cache';
    if (is_file($file) && (time() - filemtime($file)) < $ttl) {
        $raw = @file_get_contents($file);
        if ($raw !== false) {
            return unserialize($raw);
        }
    }
    $data = $loader();
    @file_put_contents($file, serialize($data), LOCK_EX);
    return $data;
}
function cache_file_forget(string $key): void
{
    $dir = sys_get_temp_dir() . '/crnp_cache';
    $file = $dir . '/' . md5($key) . '.cache';
    if (is_file($file)) {
        @unlink($file);
    }
}

/* ---------- business settings ----------
 * Cached across requests for 300s (5 min). Stale reads are acceptable for
 * business info; admin can manually clear by saving in Settings. */
function get_settings(): array
{
    return cache_file_get('business_settings', 300, function () {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $defaults = [
            'business_name'   => BRAND_NAME,
            'tagline'         => BRAND_TAGLINE,
            'address'         => 'Mabolo, Iloilo City Proper, Iloilo City, Philippines',
            'phone'           => '+63 (033) 320-0000',
            'hours'           => 'Tue–Sun · 11:00 AM – 10:00 PM (Closed Mondays)',
            'facebook_url'    => '',
            'instagram_url'   => '',
            'support_email'   => '',
            'hero_title'      => 'Your table is waiting.',
            'hero_subtitle'   => 'Order ahead for pickup, reserve a table, or book equipment for your next celebration — all from one account.',
            'about_headline'  => 'Your trusted partner for events and celebrations.',
            'about_body'      => 'From everyday meals to special gatherings, we bring quality food and reliable rental equipment to every table we serve in Iloilo City.',
            'about_stat1_num' => '10+', 'about_stat1_lbl' => 'Years Experience',
            'about_stat2_num' => '500+', 'about_stat2_lbl' => 'Events Served',
            'about_stat3_num' => '100%', 'about_stat3_lbl' => 'Satisfaction',
            'gcash_number'    => '0917 000 0000',
            'gcash_qr'        => '',
        ];
        try {
            $db = getDB();
            $s = $db->retrieve('/settings');
            $cache = is_array($s) ? array_merge($defaults, $s) : $defaults;
        } catch (Throwable $e) {
            $cache = $defaults;
        }
        return $cache;
    });
}

/* ---------- GCash payment info ---------- */
/**
 * GCash payment info shown when the customer picks GCash.
 * Returns an empty string when no number or QR is configured.
 */
function gcash_payment_info_html(): string
{
    $s   = get_settings();
    $num = trim((string) ($s['gcash_number'] ?? ''));
    $qr  = trim((string) ($s['gcash_qr'] ?? ''));
    if ($num === '' && $qr === '') {
        return '';
    }
    $qrSrc = $qr !== '' ? image_display_src($qr, 'settings') : '';
    $h  = '<div class="gcash-info" style="display:block;margin:10px 0 14px;padding:14px 16px;background:var(--surface-2);border:1px solid var(--line-2);border-radius:var(--radius-sm);text-align:center">';
    if ($qrSrc !== '') {
        $h .= '<img src="' . e($qrSrc) . '" alt="GCash QR code" style="max-width:170px;width:100%;height:auto;border-radius:8px;margin:0 auto 10px;background:#fff;padding:6px;display:block">';
    }
    if ($num !== '') {
        $h .= '<div style="font-size:13px;color:var(--muted)">Send your GCash payment to</div>';
        $h .= '<div style="font-family:var(--sans);font-size:1.3rem;font-weight:700;color:#16a34a;letter-spacing:.03em">' . e($num) . '</div>';
    }
    $h .= '</div>';
    return $h;
}

/* ---------- order tracker stepper ---------- */
function order_tracker_html(string $status): string
{
    $steps = [
        'pending'   => ['Pending', 0],
        'accepted'  => ['Accepted', 1],
        'preparing' => ['Preparing', 2],
        'ready'     => ['Ready', 3],
        'done'      => 'done',
    ];
    $cancelled = in_array($status, ['cancelled', 'cashier_cancelled'], true);
    if ($cancelled) {
        return '<div class="badge badge--muted">Cancelled</div>';
    }
    $order = ['pending','accepted','preparing','ready','done'];
    $currentIdx = array_search($status, $order, true);
    if ($currentIdx === false) {
        $currentIdx = 0;
    }
    $labels = ['Pending', 'Accepted', 'Preparing', 'Ready', 'Done'];
    $html = '<div class="tracker" role="list">';
    foreach ($labels as $i => $label) {
        $cls = '';
        if ($i < $currentIdx) {
            $cls = 'tracker__step--done';
        } elseif ($i === $currentIdx) {
            $cls = 'tracker__step--current';
        }
        $icon = $i < $currentIdx ? '✓' : ($i + 1);
        $html .= '<div class="tracker__step ' . $cls . '" role="listitem">';
        $html .= '<span class="tracker__dot">' . $icon . '</span>';
        $html .= '<span class="tracker__label">' . e($label) . '</span>';
        $html .= '</div>';
    }
    $html .= '</div>';
    return $html;
}
