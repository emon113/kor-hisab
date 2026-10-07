<?php

namespace App\Services\Ocr;

/**
 * Reads the text of an image or PDF. Swap implementations in
 * AppServiceProvider (OCR.space today; a local engine could follow).
 */
interface OcrReader
{
    /** Whether automatic reading is available at all (e.g. an API key is set). */
    public function enabled(): bool;

    /**
     * @throws OcrFailed with a message that can be shown to the user
     */
    public function read(string $path, string $mime): string;
}
