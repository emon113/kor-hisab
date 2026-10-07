<?php

namespace App\Services\SalaryCertificate;

use App\Models\SalaryCertificate;
use App\Services\Ocr\ImagePreparer;
use App\Services\Ocr\OcrFailed;
use App\Services\Ocr\OcrReader;

/**
 * Reads an uploaded certificate: shrink if needed → OCR → parse → save.
 * Reading problems never stop the user: they get a message and fill the
 * figures in themselves.
 */
final class CertificateReader
{
    public function __construct(
        private readonly OcrReader $ocr,
        private readonly ImagePreparer $preparer,
        private readonly CertificateParser $parser,
    ) {}

    public function enabled(): bool
    {
        return $this->ocr->enabled();
    }

    public function read(SalaryCertificate $certificate): void
    {
        if (! $this->ocr->enabled()) {
            $certificate->update(['status' => SalaryCertificate::UNREAD, 'ocr_error' => __('Automatic reading is not set up on this server. Enter the figures from your certificate.')]);

            return;
        }

        $path = null;
        $temporary = false;
        try {
            [$path, $mime, $temporary] = $this->preparer->prepare($certificate->absolutePath(), $certificate->mime);
            $text = $this->ocr->read($path, $mime);
            $parsed = $this->parser->parse($text);
            $certificate->update([
                'status' => $parsed['found'] ? SalaryCertificate::READ : SalaryCertificate::UNREAD,
                'ocr_text' => $text,
                'ocr_error' => $parsed['found'] ? null : __('The text was read, but no salary figures were recognised. Enter them yourself; the text is shown below to copy from.'),
                'extracted' => $parsed,
            ]);
        } catch (OcrFailed $e) {
            $certificate->update(['status' => SalaryCertificate::UNREAD, 'ocr_error' => $e->getMessage()]);
        } finally {
            if ($temporary && $path) {
                @unlink($path);
            }
        }
    }
}
