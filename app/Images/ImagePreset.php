<?php

declare(strict_types=1);

namespace App\Images;

/**
 * A resize/compress target: the longest edge an image is scaled down to, and
 * the JPEG byte ceiling it must fit. Presets are the only place these numbers
 * live, so #21 (products) and #24 (rentals) reuse the same discipline.
 */
enum ImagePreset: string
{
    /**
     * The official GCash QR uploaded in admin settings. 800 px keeps the
     * symbol crisp on a phone screen; the 300 KB ceiling matches the proof
     * pipeline's client-side budget.
     */
    case GcashQr = 'gcash_qr';

    public function maxEdge(): int
    {
        return match ($this) {
            self::GcashQr => 800,
        };
    }

    public function maxBytes(): int
    {
        return match ($this) {
            self::GcashQr => 300 * 1024,
        };
    }

    /**
     * The smallest long edge an image is allowed to land on — a legibility
     * floor. Never upscales: an image already below this is kept as-is.
     */
    public function minEdge(): int
    {
        return match ($this) {
            self::GcashQr => 720,
        };
    }
}
