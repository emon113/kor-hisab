<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable(['original_name', 'path', 'mime', 'size', 'status', 'ocr_text', 'ocr_error', 'extracted', 'data', 'confirmed_at'])]
#[Hidden(['path', 'ocr_text'])]
class SalaryCertificate extends Model
{
    public const DISK = 'local';

    public const READ = 'read';

    public const UNREAD = 'unread';

    public const CONFIRMED = 'confirmed';

    protected static function booted(): void
    {
        // The file goes with the record.
        static::deleted(fn (self $certificate) => Storage::disk(self::DISK)->delete($certificate->path));
    }

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'extracted' => 'array',
            'data' => 'array',
            'confirmed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function belongsToUser(?User $user): bool
    {
        return $user !== null && (int) $this->user_id === (int) $user->id;
    }

    public function isPdf(): bool
    {
        return $this->mime === 'application/pdf';
    }

    public function absolutePath(): string
    {
        return Storage::disk(self::DISK)->path($this->path);
    }

    /** A short title: the employer and year if known, else the file name. */
    public function title(): string
    {
        $employer = $this->data['employer'] ?? $this->extracted['meta']['employer'] ?? null;
        $year = $this->data['year'] ?? $this->extracted['meta']['year'] ?? null;

        return trim(($employer ?: pathinfo($this->original_name, PATHINFO_FILENAME)).($year ? ' · '.WealthStatement::yearLabel($year) : ''));
    }
}
