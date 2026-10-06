<?php

declare(strict_types=1);

namespace App\Settings\Actions;

use App\Images\ImageCompressor;
use App\Images\ImagePreset;
use App\Settings\SettingsRepository;
use Illuminate\Http\UploadedFile;

/**
 * Persists the GCash payment block: the number plus the official QR image.
 *
 * The uploaded QR is resized and re-encoded server-side, then stored as base64
 * in RTDB — no raw file upload ever reaches the datastore, and the official
 * image is never re-rendered (it is scaled, not restyled). Submitting without a
 * new file keeps the stored QR, so the number can be edited on its own.
 */
final readonly class UpdatePayment
{
    public function __construct(
        private SettingsRepository $settings,
        private ImageCompressor $compressor,
    ) {}

    public function __invoke(string $number, ?UploadedFile $qr): void
    {
        $current = $this->settings->current();

        $storedQr = $current->gcashQr;

        if ($qr !== null) {
            $bytes = $qr->get();

            if (! is_string($bytes) || $bytes === '') {
                throw new \InvalidArgumentException('The QR upload could not be read.');
            }

            $storedQr = $this->compressor->compressToDataUrl($bytes, ImagePreset::GcashQr);
        }

        $this->settings->save($current->withPayment(trim($number), $storedQr));
    }
}
