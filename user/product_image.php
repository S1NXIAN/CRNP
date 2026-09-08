<?php

/**
 * product_image.php — serves product/rent-item images from Firebase b64 data.
 * ?id=KEY&table=products (default) or table=rent_items
 * Cached rendition on disk; revalidated against the database once stale so
 * out-of-band edits converge instead of serving stale bytes forever.
 */
require_once __DIR__ . '/../init.php';

$id    = trim((string) ($_GET['id'] ?? ''));
$table = trim((string) ($_GET['table'] ?? 'products'));

if ($id === '' || !preg_match('/^[a-zA-Z0-9_\\-]+$/', $id)) {
    http_response_code(400);
    exit;
}

if (!in_array($table, ['products', 'rent_items'], true)) {
    $table = 'products';
}

$cacheFile = product_image_cache_path($id, $table);
$cacheDir = dirname($cacheFile);
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0755, true);
}

function proxy_serve(string $cacheFile, string $mime, string $etag, int $mtime): void
{
    $ifNone = trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
    if ($ifNone !== '' && hash_equals($etag, $ifNone)) {
        http_response_code(304);
        exit;
    }
    header('Content-Type: ' . $mime);
    header('Cache-Control: public, max-age=300, must-revalidate');
    header('ETag: ' . $etag);
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
    header('Content-Length: ' . filesize($cacheFile));
    readfile($cacheFile);
    exit;
}

function proxy_mime(string $raw): string
{
    if (str_starts_with($raw, "\x89PNG\r\n\x1a\n")) {
        return 'image/png';
    }
    if (str_starts_with($raw, 'RIFF') && substr($raw, 8, 4) === 'WEBP') {
        return 'image/webp';
    }
    if (str_starts_with($raw, "\xff\xd8\xff")) {
        return 'image/jpeg';
    }
    if (str_starts_with($raw, 'GIF87a') || str_starts_with($raw, 'GIF89a')) {
        return 'image/gif';
    }
    return 'image/jpeg';
}

/* Fresh cache wins without a database hit. */
if (is_file($cacheFile) && filesize($cacheFile) > 0 && (time() - (int) filemtime($cacheFile)) < 300) {
    $meta = @unserialize((string) @file_get_contents($cacheFile . '.meta'));
    $mime = is_array($meta) ? (string) ($meta['mime'] ?? 'image/jpeg') : 'image/jpeg';
    $etag = is_array($meta) ? (string) ($meta['etag'] ?? '') : '';
    if ($etag === '') {
        $etag = '"' . sha1_file($cacheFile) . '"';
    }
    proxy_serve($cacheFile, $mime, $etag, (int) filemtime($cacheFile));
}

/* Fetch from Firebase. */
$db   = getDB();
$data = $db->retrieve('/' . $table . '/' . $id);
if (!is_array($data)) {
    http_response_code(404);
    exit;
}

$image = $data['image'] ?? '';
if (!is_string($image) || !str_starts_with($image, 'b64:')) {
    http_response_code(404);
    exit;
}

$raw = @base64_decode(substr($image, 4), true);
if ($raw === false || strlen($raw) < 8) {
    http_response_code(404);
    exit;
}

$mime = proxy_mime($raw);
$etag = '"' . sha1($raw) . '"';

/* Converge stale cache; keep serving it when the database is unreachable. */
if (is_file($cacheFile) && filesize($cacheFile) > 0) {
    $cached = @file_get_contents($cacheFile);
    if ($cached !== false && hash_equals(sha1($cached), trim($etag, '"'))) {
        @touch($cacheFile);
        proxy_serve($cacheFile, $mime, $etag, (int) filemtime($cacheFile));
    }
}

/* Cache to disk. */
file_put_contents($cacheFile, $raw);
file_put_contents($cacheFile . '.meta', serialize(['mime' => $mime, 'etag' => $etag]));
product_image_cache_prune($table);

/* Serve. */
header('Content-Type: ' . $mime);
header('Cache-Control: public, max-age=300, must-revalidate');
header('ETag: ' . $etag);
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', time()) . ' GMT');
header('Content-Length: ' . strlen($raw));
echo $raw;
