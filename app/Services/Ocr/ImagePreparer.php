<?php

namespace App\Services\Ocr;

/**
 * Makes a file small enough for the OCR service (OCR.space free plan: 1 MB).
 *
 * Images are scaled down and saved as JPEG until they fit; text stays
 * readable well below the camera's resolution. PDFs cannot be shrunk here,
 * so a PDF over the limit is refused with a clear message.
 */
final class ImagePreparer
{
    private const START_EDGE = 2200;   // px on the long side

    private const MIN_EDGE = 900;

    public function __construct(private readonly int $maxBytes) {}

    /**
     * @return array{0: string, 1: string, 2: bool} path, mime, and whether the path is a temporary copy
     */
    public function prepare(string $path, string $mime): array
    {
        if (filesize($path) <= $this->maxBytes) {
            return [$path, $mime, false];
        }
        if ($mime === 'application/pdf') {
            throw new OcrFailed(__('This PDF is larger than :size, the most the reading service accepts. Upload a photo or screenshot of the certificate instead, or enter the figures yourself.', ['size' => $this->readable()]));
        }

        $image = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
            default => false,
        };
        if (! $image) {
            throw new OcrFailed(__('This image could not be opened. Try a JPG or PNG.'));
        }

        $tmp = tempnam(sys_get_temp_dir(), 'kh-ocr-').'.jpg';
        $width = imagesx($image);
        $height = imagesy($image);
        $edge = min(self::START_EDGE, max($width, $height));
        try {
            do {
                $scale = $edge / max($width, $height);
                // imagescale() returns false when asked for the same size, so only scale when shrinking.
                $resized = $scale < 1
                    ? imagescale($image, max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)), IMG_BILINEAR_FIXED)
                    : $image;
                if (! $resized) {
                    throw new OcrFailed(__('This image could not be opened. Try a JPG or PNG.'));
                }
                imagejpeg($resized, $tmp, 82);
                if ($resized !== $image) {
                    imagedestroy($resized);
                }
                clearstatcache(true, $tmp);
                $edge = (int) round($edge * 0.8);
            } while (filesize($tmp) > $this->maxBytes && $edge >= self::MIN_EDGE);
        } finally {
            imagedestroy($image);
        }

        if (filesize($tmp) > $this->maxBytes) {
            @unlink($tmp);
            throw new OcrFailed(__('This image is too large to read. Try a smaller photo.'));
        }

        return [$tmp, 'image/jpeg', true];
    }

    private function readable(): string
    {
        return round($this->maxBytes / 1048576, 1).' MB';
    }
}
