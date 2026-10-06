<?php

declare(strict_types=1);

namespace App\Images;

use GdImage;
use InvalidArgumentException;

/**
 * Server-side image normalisation: validate, bound, flatten to an opaque JPEG,
 * and step the quality down until the bytes fit the preset ceiling.
 *
 * This is the one image seam the admin uploads route through — settings (#20),
 * products (#21), and rentals (#24) share it, so a raw multi-megabyte file
 * never reaches RTDB. The prototype's GD quality-stepping implementation is the
 * reference (`crnp-notes/prototype-reference.md` §5).
 */
final class ImageCompressor
{
    /**
     * JPEG quality steps, high to low. The first that fits the byte ceiling
     * wins; if none fit, the smallest produced (the last step) is returned.
     *
     * @var list<int>
     */
    private const QUALITY_STEPS = [82, 72, 65, 60];

    /**
     * @param  string  $bytes  raw image bytes from the upload
     * @return string  a JPEG (no `data:` prefix), sized to the preset
     */
    public function compress(string $bytes, ImagePreset $preset): string
    {
        $source = $this->decode($bytes);
        $scaled = $this->scaleDown($source, $preset->maxEdge());
        $flat = $this->flatten($scaled);

        if ($scaled !== $source) {
            imagedestroy($scaled);
        }

        imagedestroy($source);

        $encoded = $this->encodeToCeiling($flat, $preset->maxBytes());

        imagedestroy($flat);

        return $encoded;
    }

    /**
     * Wrap the compressed JPEG as a `data:` URL for storage in RTDB.
     */
    public function compressToDataUrl(string $bytes, ImagePreset $preset): string
    {
        return 'data:image/jpeg;base64,' . base64_encode($this->compress($bytes, $preset));
    }

    private function decode(string $bytes): GdImage
    {
        $info = @getimagesizefromstring($bytes);

        if ($info === false) {
            throw new InvalidArgumentException('The upload is not a readable image.');
        }

        if (! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF], true)) {
            throw new InvalidArgumentException('The upload is not a supported image type.');
        }

        $image = @imagecreatefromstring($bytes);

        if ($image === false) {
            throw new InvalidArgumentException('The upload could not be decoded.');
        }

        return $image;
    }

    /**
     * Scale the image so its longest edge is at most $maxEdge. Images already
     * inside the bound are returned untouched (never upscaled).
     */
    private function scaleDown(GdImage $image, int $maxEdge): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $longest = max($width, $height);

        if ($longest <= $maxEdge) {
            return $image;
        }

        $ratio = $maxEdge / $longest;
        $targetWidth = max(1, (int) round($width * $ratio));
        $targetHeight = max(1, (int) round($height * $ratio));

        $scaled = imagescale($image, $targetWidth, $targetHeight, IMG_BILINEAR_FIXED);

        if ($scaled === false) {
            throw new InvalidArgumentException('The image could not be scaled.');
        }

        return $scaled;
    }

    /**
     * Copy the image onto an opaque white canvas, dropping any alpha channel so
     * the JPEG encodes predictably.
     */
    private function flatten(GdImage $image): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);

        $flat = imagecreatetruecolor($width, $height);

        if ($flat === false) {
            throw new InvalidArgumentException('The image canvas could not be created.');
        }

        $white = imagecolorallocate($flat, 255, 255, 255);

        if ($white === false) {
            throw new InvalidArgumentException('The image canvas could not be filled.');
        }

        imagefill($flat, 0, 0, $white);
        imagecopy($flat, $image, 0, 0, 0, 0, $width, $height);

        return $flat;
    }

    /**
     * Encode at descending qualities; return the first result within the byte
     * ceiling, or the smallest produced when none fit.
     */
    private function encodeToCeiling(GdImage $image, int $maxBytes): string
    {
        $smallest = '';

        foreach (self::QUALITY_STEPS as $quality) {
            ob_start();
            imagejpeg($image, null, $quality);
            $encoded = (string) ob_get_clean();

            if ($encoded === '') {
                throw new InvalidArgumentException('The image could not be encoded.');
            }

            if (strlen($encoded) <= $maxBytes) {
                return $encoded;
            }

            $smallest = $encoded;
        }

        return $smallest;
    }
}
