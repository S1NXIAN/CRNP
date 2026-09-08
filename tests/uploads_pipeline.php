<?php

/**
 * uploads_pipeline.php — offline checks for the single upload seam.
 *
 * Covers: bounded normalized bytes, stripped metadata, content-addressed
 * retry convergence, replace-means-delete, no local copy for b64 values,
 * validation-failure leaves nothing, proxy cache discipline, and paged
 * lists staying free of embedded payloads. No network, no database.
 */
declare(strict_types=1);

if (!defined('UPLOAD_ROOT')) {
    define('UPLOAD_ROOT', sys_get_temp_dir() . '/crnp_upload_test_' . getmypid());
}
if (!defined('UPLOAD_WEB')) {
    define('UPLOAD_WEB', '/uploads');
}

require __DIR__ . '/../includes/functions.php';

require_once __DIR__ . '/helpers.php';

$root = (string) constant('UPLOAD_ROOT');
foreach (['user/profile', 'settings', 'user/bookings', 'admin/item', 'cache/products', 'cache/rent_items'] as $sub) {
    @mkdir($root . '/' . $sub, 0775, true);
}

function test_jpeg(int $w, int $h, int $quality = 100): string
{
    $img = imagecreatetruecolor($w, $h);
    $red = imagecolorallocate($img, 200, 40, 40);
    imagefill($img, 0, 0, $red === false ? 0 : $red);
    for ($i = 0; $i < 200; $i++) {
        $c = imagecolorallocate($img, $i % 256, ($i * 7) % 256, ($i * 13) % 256);
        imageline($img, 0, $i, $w, $h - $i, $c === false ? 0 : $c);
    }
    ob_start();
    imagejpeg($img, null, $quality);
    $out = (string) ob_get_clean();
    imagedestroy($img);

    return $out;
}

function test_files_in(string $dir): array
{
    $files = (array) glob(rtrim($dir, '/') . '/*');
    $out = [];
    foreach ($files as $file) {
        if (is_file($file) && !str_ends_with($file, '.tmp')) {
            $out[] = $file;
        }
    }

    return $out;
}

function test_fake_upload(string $field, string $raw, string $name = 'photo.jpg'): void
{
    $tmp = tempnam(sys_get_temp_dir(), 'crnp_up_');
    file_put_contents($tmp, $raw);
    $_FILES[$field] = [
        'name' => $name,
        'type' => 'image/jpeg',
        'tmp_name' => $tmp,
        'error' => UPLOAD_ERR_OK,
        'size' => strlen($raw),
    ];
}

function test_clear_upload(string $field): void
{
    if (isset($_FILES[$field]['tmp_name']) && is_file((string) $_FILES[$field]['tmp_name'])) {
        @unlink((string) $_FILES[$field]['tmp_name']);
    }
    unset($_FILES[$field]);
}

$large = test_jpeg(2000, 1500);
check('large fixture exceeds menu budget before normalize', strlen($large) > 100 * 1024);

$menu = upload_normalize_bytes($large, 'admin/item');
$info = @getimagesizefromstring($menu);
check('menu bounded dimensions', $info !== false && max($info[0], $info[1]) <= 1280);
check('menu bounded bytes', strlen($menu) <= 400 * 1024);
check('menu normalized to jpeg', str_starts_with($menu, "\xff\xd8\xff"));

$avatar = upload_normalize_bytes($large, 'user/profile');
$info = @getimagesizefromstring($avatar);
check('avatar smallest dimensions', $info !== false && max($info[0], $info[1]) <= 384);
check('avatar bounded bytes', strlen($avatar) <= 100 * 1024);

$receipt = upload_normalize_bytes($large, 'user/bookings');
$info = @getimagesizefromstring($receipt);
check('receipt medium dimensions', $info !== false && max($info[0], $info[1]) <= 1024);
check('receipt bounded bytes', strlen($receipt) <= 300 * 1024);

$qr = upload_normalize_bytes($large, 'settings');
$info = @getimagesizefromstring($qr);
check('qr small dimensions', $info !== false && max($info[0], $info[1]) <= 512);
check('qr bounded bytes', strlen($qr) <= 120 * 1024);

$tagged = $large;
$exifMarker = 'Exif' . "\0\0" . 'GPSLAT12345HIDEME';
$tagged = substr($tagged, 0, 2) . "\xff\xe1" . pack('n', strlen($exifMarker) + 2) . $exifMarker . substr($tagged, 2);
$stripped = upload_normalize_bytes($tagged, 'admin/item');
check('metadata stripped', !str_contains($stripped, 'GPSLAT12345HIDEME'));

$again = upload_normalize_bytes($large, 'admin/item');
check('retry converges byte-identical', hash('sha256', $menu) === hash('sha256', $again));

test_fake_upload('profile_image', $large);
$first = save_upload('profile_image', $root . '/user/profile');
test_clear_upload('profile_image');
$before = count(test_files_in($root . '/user/profile'));
test_fake_upload('profile_image', $large);
$second = save_upload('profile_image', $root . '/user/profile');
test_clear_upload('profile_image');
$after = count(test_files_in($root . '/user/profile'));
check('avatar retry reuses stored object', $first === $second && $before === $after);

$small = test_jpeg(800, 600);
test_fake_upload('profile_image', $small);
$third = save_upload('profile_image', $root . '/user/profile');
test_clear_upload('profile_image');
check('distinct avatar yields distinct object', $third !== $first);
upload_retire_file('user/profile', $first, $third);
check('avatar replace deletes predecessor', !is_file($root . '/user/profile/' . $first) && is_file($root . '/user/profile/' . $third));
upload_retire_file('user/profile', $third, $third);
check('identical retry keeps file', is_file($root . '/user/profile/' . $third));

test_fake_upload('gcash_qr', $large);
$qrFirst = save_upload('gcash_qr', $root . '/settings', ['jpg', 'jpeg', 'png', 'webp'], 2);
test_clear_upload('gcash_qr');
test_fake_upload('gcash_qr', $small);
$qrSecond = save_upload('gcash_qr', $root . '/settings', ['jpg', 'jpeg', 'png', 'webp'], 2);
test_clear_upload('gcash_qr');
upload_retire_file('settings', $qrFirst, $qrSecond);
check('qr replace deletes predecessor', !is_file($root . '/settings/' . $qrFirst));
upload_retire_file('settings', $qrSecond, '');
check('qr remove deletes file', !is_file($root . '/settings/' . $qrSecond));

$bookingFilesBefore = count(test_files_in($root . '/user/bookings'));
test_fake_upload('receipt', $large);
$receiptB64 = upload_to_base64('receipt', $root . '/user/bookings');
test_clear_upload('receipt');
$bookingFilesAfter = count(test_files_in($root . '/user/bookings'));
check('receipt stores single b64 object', is_string($receiptB64) && str_starts_with($receiptB64, 'b64:'));
check('receipt keeps no local copy', $bookingFilesBefore === $bookingFilesAfter);

$menuFilesBefore = count(test_files_in($root . '/admin/item'));
test_fake_upload('image', $large);
$menuB64 = upload_to_base64('image', $root . '/admin/item');
test_clear_upload('image');
check('menu retry converges', upload_normalize_bytes($large, 'admin/item') === (string) base64_decode(substr((string) $menuB64, 4)));
check('menu keeps no local copy', count(test_files_in($root . '/admin/item')) === $menuFilesBefore);

test_fake_upload('image', $large);
$_FILES['image']['size'] = 6 * 1024 * 1024;
$failed = false;
try {
    upload_to_base64('image', $root . '/admin/item');
} catch (Throwable $ex) {
    $failed = true;
}
test_clear_upload('image');
check('oversize validation leaves nothing', $failed && count(test_files_in($root . '/admin/item')) === $menuFilesBefore);

$_FILES['receipt'] = ['name' => 'x.txt', 'type' => 'text/plain', 'tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0];
check('no file returns null without storing', upload_to_base64('receipt', $root . '/user/bookings') === null);
unset($_FILES['receipt']);

$polyglot = 'not an image at all';
$tmp = tempnam(sys_get_temp_dir(), 'crnp_up_');
file_put_contents($tmp, $polyglot);
$_FILES['receipt'] = ['name' => 'evil.php', 'type' => 'image/jpeg', 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => strlen($polyglot)];
$rejected = false;
try {
    upload_to_base64('receipt', $root . '/user/bookings');
} catch (Throwable $ex) {
    $rejected = true;
}
@unlink($tmp);
unset($_FILES['receipt']);
check('invalid polyglot rejected', $rejected);

$cropSrc = 'data:image/jpeg;base64,' . base64_encode(test_jpeg(900, 900));
$cropped = upload_cropped_to_base64($cropSrc, 'admin/item');
check('cropped input normalized', is_string($cropped) && str_starts_with($cropped, 'b64:'));
if (is_string($cropped)) {
    $raw = (string) base64_decode(substr($cropped, 4));
    $info = @getimagesizefromstring($raw);
    check('cropped bounded', $info !== false && max($info[0], $info[1]) <= 1280 && strlen($raw) <= 400 * 1024);
}

$proxyUrl = product_image_url((string) $menuB64, 'abc123', 'products');
check('menu list uses proxy without payload', str_contains((string) $proxyUrl, 'product_image.php') && !str_contains((string) $proxyUrl, 'b64:'));
$receiptSrc = image_display_src((string) $receiptB64, 'user/bookings');
check('receipt resolves through display helper', str_starts_with((string) $receiptSrc, 'data:image/jpeg;base64,'));

$cacheId = 'testitem123';
$cachePath = product_image_cache_path($cacheId, 'products');
@mkdir(dirname($cachePath), 0775, true);
file_put_contents($cachePath, $menu);
file_put_contents($cachePath . '.meta', serialize(['mime' => 'image/jpeg', 'etag' => '"' . sha1($menu) . '"']));
check('cache stores rendition', is_file($cachePath));
product_image_cache_invalidate($cacheId, 'products');
check('update invalidates cached rendition', !is_file($cachePath));
file_put_contents($cachePath, $menu);
file_put_contents($cachePath . '.meta', serialize(['mime' => 'image/jpeg']));
product_image_cache_invalidate($cacheId, 'products');
check('delete removes cached rendition', !is_file($cachePath) && !is_file($cachePath . '.meta'));

$cacheDir = $root . '/cache/products';
for ($i = 0; $i < 5; $i++) {
    file_put_contents($cacheDir . '/old' . $i . '.img', str_repeat('x', 100));
    touch($cacheDir . '/old' . $i . '.img', time() - 1000 + $i);
}
product_image_cache_prune('products', 3, 100 * 1024 * 1024);
$remaining = array_filter((array) glob($cacheDir . '/*.img'), 'is_file');
check('cache bounded by count', count($remaining) <= 3);

foreach ((array) glob($root . '/*/*') as $file) {
    if (is_file($file)) {
        @unlink($file);
    }
}
foreach ((array) glob($root . '/cache/*/*.img*') as $file) {
    if (is_file($file)) {
        @unlink($file);
    }
}

exit($fails === 0 ? 0 : 1);
