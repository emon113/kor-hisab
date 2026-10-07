<?php

namespace App\Services\Ocr;

use RuntimeException;

/** Reading failed; the message is safe to show to the user. */
class OcrFailed extends RuntimeException {}
