<?php

namespace App\Models;

use App\Services\Wealth\WealthReconciler;
use App\Support\Lang;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tax_year', 'opening_net_wealth', 'receipts', 'expenses', 'liabilities', 'assets', 'notes'])]
class WealthStatement extends Model
{
    public const GROUPS = ['receipts', 'expenses', 'liabilities', 'assets'];

    protected function casts(): array
    {
        return [
            'opening_net_wealth' => 'integer',
            'receipts' => 'array',
            'expenses' => 'array',
            'liabilities' => 'array',
            'assets' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function data(): array
    {
        return $this->only(self::GROUPS);
    }

    public static function yearLabel(string $year): string
    {
        return Lang::label('AY :year', ['year' => $year]);
    }

    public static function asOnLabel(string $year): string
    {
        return Lang::label('30 June :year', ['year' => substr(WealthReconciler::yearEnd($year), 0, 4)]);
    }
}
