<?php

namespace App\Services\Ocr;

/** Used when no OCR service is configured: the user types the figures in. */
final class NullReader implements OcrReader
{
    public function enabled(): bool
    {
        return false;
    }

    public function read(string $path, string $mime): string
    {
        throw new OcrFailed(__('Automatic reading is not set up on this server. Enter the figures from your certificate.'));
    }
}
