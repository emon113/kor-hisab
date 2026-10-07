<?php

namespace App\Services\Ocr;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * OCR.space (https://ocr.space/ocrapi). isTable keeps a certificate's
 * columns apart with tabs, which CertificateParser relies on.
 */
final class OcrSpaceReader implements OcrReader
{
    private const FILETYPES = ['application/pdf' => 'PDF', 'image/jpeg' => 'JPG', 'image/png' => 'PNG', 'image/webp' => 'WEBP'];

    public function __construct(
        private readonly string $key,
        private readonly string $endpoint,
        private readonly int $engine = 2,
        private readonly int $timeout = 60,
    ) {}

    public function enabled(): bool
    {
        return $this->key !== '';
    }

    public function read(string $path, string $mime): string
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders(['apikey' => $this->key])
                ->attach('file', fopen($path, 'r'), basename($path))
                ->post($this->endpoint, [
                    'language' => 'eng',
                    'isTable' => 'true',
                    'scale' => 'true',
                    'detectOrientation' => 'true',
                    'OCREngine' => (string) $this->engine,
                    'filetype' => self::FILETYPES[$mime] ?? 'JPG',
                ]);
        } catch (ConnectionException $e) {
            Log::warning('OCR.space unreachable', ['error' => $e->getMessage()]);
            throw new OcrFailed(__('The reading service did not respond. Enter the figures yourself, or try again later.'));
        }

        $json = $response->json() ?? [];
        if (! $response->successful() || ($json['IsErroredOnProcessing'] ?? false) || (int) ($json['OCRExitCode'] ?? 0) > 2) {
            $detail = $json['ErrorMessage'] ?? $response->body();
            Log::warning('OCR.space failed', ['status' => $response->status(), 'error' => is_array($detail) ? implode(' ', $detail) : $detail]);
            throw new OcrFailed(__('The certificate could not be read automatically. Enter the figures yourself.'));
        }

        $text = implode("\n", array_map(fn ($p) => (string) ($p['ParsedText'] ?? ''), $json['ParsedResults'] ?? []));
        if (trim($text) === '') {
            throw new OcrFailed(__('No text was found in this file. A sharper photo or the original PDF usually works better.'));
        }

        return $text;
    }
}
