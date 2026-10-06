<?php

declare(strict_types=1);

use App\Images\ImageCompressor;
use App\Images\ImagePreset;

/**
 * A deterministic, photo-like PNG so the byte-ceiling step is exercised without
 * depending on a fixture file or randomness.
 */
function noisyPng(int $width, int $height): string
{
    $image = imagecreatetruecolor($width, $height);
    mt_srand(20261006);

    for ($y = 0; $y < $height; $y += 4) {
        for ($x = 0; $x < $width; $x += 4) {
            $color = imagecolorallocate($image, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255));
            imagefilledrectangle($image, $x, $y, $x + 3, $y + 3, $color);
        }
    }

    ob_start();
    imagepng($image);
    $bytes = ob_get_clean();
    imagedestroy($image);

    return (string) $bytes;
}

it('shrinks a large image to the preset longest edge as a JPEG', function (): void {
    $compressed = app(ImageCompressor::class)->compress(noisyPng(1600, 1200), ImagePreset::GcashQr);

    $info = getimagesizefromstring($compressed);

    expect($info)->not->toBeFalse()
        ->and($info[2])->toBe(IMAGETYPE_JPEG)
        ->and(max($info[0], $info[1]))->toBe(800);
});

it('keeps the compressed image under the preset byte ceiling', function (): void {
    $compressed = app(ImageCompressor::class)->compress(noisyPng(1600, 1200), ImagePreset::GcashQr);

    expect(strlen($compressed))->toBeLessThanOrEqual(ImagePreset::GcashQr->maxBytes());
});

it('never upscales an image already inside the preset bounds', function (): void {
    $compressed = app(ImageCompressor::class)->compress(noisyPng(300, 200), ImagePreset::GcashQr);

    $info = getimagesizefromstring($compressed);

    expect($info)->not->toBeFalse()
        ->and($info[0])->toBe(300)
        ->and($info[1])->toBe(200);
});

it('rejects bytes that are not a readable image', function (): void {
    expect(fn(): string => app(ImageCompressor::class)->compress('not an image', ImagePreset::GcashQr))
        ->toThrow(InvalidArgumentException::class);
});
