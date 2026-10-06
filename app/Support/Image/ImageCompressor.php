<?php

declare(strict_types=1);

namespace App\Support\Image;

use GdImage;
use InvalidArgumentException;
use RuntimeException;

/**
 * Resizes an uploaded image with GD so no raw upload reaches RTDB.
 *
 * The longest edge is scaled to ~800px, JPEG quality is stepped down until the
 * encoded bytes fit under ~300KB, and the longest edge is never taken below a
 * 720px floor (a source smaller than the floor is left at its own size — this
 * never upscales). The result is JPEG binary; callers store its base64.
 *
 * Shared with the settings slice (#20); the two definitions are reconciled at
 * merge. See `/home/xian/crnp-notes/foundation-web.md` §6.
 */
final class ImageCompressor
{
    private const MAX_EDGE = 800;

    private const FLOOR_EDGE = 720;

    private const MAX_BYTES = 300 * 1024;

    private const START_QUALITY = 85;

    private const QUALITY_STEP = 7;

    private const MIN_QUALITY = 40;

    /**
     * The edge decrement tried when quality alone cannot reach the byte ceiling.
     */
    private const EDGE_STEP = 40;

    /**
     * @return string JPEG binary
     */
    public function compress(string $binary): string
    {
        $source = @imagecreatefromstring($binary);

        if (! $source instanceof GdImage) {
            throw new InvalidArgumentException('The uploaded image could not be decoded.');
        }

        $width = imagesx($source);
        $height = imagesy($source);

        try {
            $edge = $this->targetEdge($width, $height);

            while (true) {
                $encoded = $this->encodeAtEdge($source, $width, $height, $edge);

                // Accept once the bytes fit, or once the 720px floor is reached
                // and no further shrink is allowed.
                if (strlen($encoded) <= self::MAX_BYTES || $edge <= self::FLOOR_EDGE) {
                    return $encoded;
                }

                $edge = max(self::FLOOR_EDGE, $edge - self::EDGE_STEP);
            }
        } finally {
            imagedestroy($source);
        }
    }

    /**
     * @return string base64 of the compressed JPEG
     */
    public function compressToBase64(string $binary): string
    {
        return base64_encode($this->compress($binary));
    }

    /**
     * The longest edge to target: shrink to the max edge, but never below the
     * floor and never above the source's own longest edge.
     */
    private function targetEdge(int $width, int $height): int
    {
        $long = max($width, $height);
        $target = min($long, self::MAX_EDGE);

        if ($target < self::FLOOR_EDGE) {
            $target = min(self::FLOOR_EDGE, $long);
        }

        return $target;
    }

    private function encodeAtEdge(GdImage $source, int $width, int $height, int $edge): string
    {
        $scale = $edge / max($width, $height);
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);

        if (! $canvas instanceof GdImage) {
            throw new RuntimeException('Unable to allocate an image canvas.');
        }

        $white = imagecolorallocate($canvas, 255, 255, 255);

        if ($white === false) {
            imagedestroy($canvas);

            throw new RuntimeException('Unable to allocate an image colour.');
        }

        // JPEG has no alpha channel; flatten onto white so transparent PNGs
        // do not turn black.
        imagefilledrectangle($canvas, 0, 0, $targetWidth, $targetHeight, $white);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        $quality = self::START_QUALITY;
        $data = '';

        do {
            ob_start();
            imagejpeg($canvas, null, $quality);
            $data = (string) ob_get_clean();

            if (strlen($data) <= self::MAX_BYTES) {
                break;
            }

            $quality -= self::QUALITY_STEP;
        } while ($quality >= self::MIN_QUALITY);

        imagedestroy($canvas);

        return $data;
    }
}
