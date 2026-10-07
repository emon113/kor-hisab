<?php

namespace App\Models;

use App\Support\Lang;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['title', 'notes', 'tax_year', 'category', 'gross_income', 'liability', 'payable', 'effective_rate', 'inputs', 'summary'])]
class Calculation extends Model
{
    protected function casts(): array
    {
        return [
            'inputs' => 'array',
            'summary' => 'array',
            'gross_income' => 'integer',
            'liability' => 'integer',
            'payable' => 'integer',
            'effective_rate' => 'float',
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

    /**
     * Columns to persist from a freshly built TaxReport. The server recomputes on save,
     * so stored figures always match the rules — the browser is never trusted for numbers.
     */
    public static function attributesFromReport(array $report, string $title, ?string $notes): array
    {
        $s = $report['summary'];
        $inv = $report['investments'];

        return [
            'title' => $title,
            'notes' => $notes,
            'tax_year' => $report['input']['year'],
            'category' => $report['input']['category'],
            'gross_income' => (int) $s['gross'],
            'liability' => (int) $s['liability'],
            'payable' => (int) $s['payable'],
            'effective_rate' => (float) $s['effective_rate'],
            'inputs' => $report['input'],
            'summary' => [
                'taxable' => $s['taxable'],
                'gross_tax' => $s['gross_tax'],
                'rebate' => $s['rebate'],
                'marginal_rate' => $s['marginal_rate'],
                'take_home_monthly' => $s['take_home_monthly'],
                'investment_eligible' => $inv['eligible'],
                'investment_needed' => $inv['needed'],
                'investment_gap' => $inv['gap'],
            ],
        ];
    }

    public function yearLabel(): string
    {
        return Lang::label(config("tax.years.{$this->tax_year}.label", $this->tax_year));
    }
}
