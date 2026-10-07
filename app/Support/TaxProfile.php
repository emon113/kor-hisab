<?php

namespace App\Support;

use App\Models\User;
use App\Services\Tax\SalaryPackage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * A user's defaults, applied on every page: who they are for tax purposes,
 * their usual salary, and how they like numbers and language shown.
 *
 * Pages start from these values and the user can still change anything on
 * the page itself. Guests get the defaults below.
 */
final class TaxProfile
{
    public const DEFAULTS = [
        // Tax profile
        'category' => 'general',
        'disabled_children' => 0,
        'new_taxpayer' => false,
        // Salary profile (optional)
        'monthly_salary' => null,
        'basic_pct' => 60,
        'bonus_count' => 2,
        'bonus_base' => 'basic',
        'employer_pf' => null,
        'employer' => null,
        // Display (null = follow the browser's current choice)
        'locale' => null,
        'digits' => null,
        'grouping' => null,
    ];

    public const TAX_KEYS = ['category', 'disabled_children', 'new_taxpayer', 'monthly_salary', 'basic_pct', 'bonus_count', 'bonus_base', 'employer_pf', 'employer'];

    public const DISPLAY_KEYS = ['locale', 'digits', 'grouping'];

    private function __construct(private readonly array $values) {}

    public static function for(?User $user): self
    {
        return new self(array_replace(self::DEFAULTS, array_intersect_key($user?->preferences ?? [], self::DEFAULTS)));
    }

    public static function taxRules(): array
    {
        $money = ['nullable', 'numeric', 'min:0', 'max:1000000000'];

        return [
            'category' => ['required', Rule::in(array_keys(config('tax.categories')))],
            'disabled_children' => ['required', 'integer', 'min:0', 'max:10'],
            'new_taxpayer' => ['boolean'],
            'monthly_salary' => $money,
            'basic_pct' => ['required', 'numeric', 'min:1', 'max:100'],
            'bonus_count' => ['required', 'numeric', 'min:0', 'max:12'],
            'bonus_base' => ['required', Rule::in(SalaryPackage::BONUS_BASES)],
            'employer_pf' => $money,
            'employer' => ['nullable', 'string', 'max:120'],
        ];
    }

    public static function displayRules(): array
    {
        return [
            'locale' => ['required', Rule::in(['en', 'bn'])],
            'digits' => ['required', Rule::in(['bn', 'latin'])],
            'grouping' => ['required', Rule::in(['intl', 'lakh'])],
        ];
    }

    public function get(string $key): mixed
    {
        return $this->values[$key] ?? null;
    }

    public function all(): array
    {
        return $this->values;
    }

    /** Inputs the tax engine understands. */
    public function taxInput(): array
    {
        return [
            'category' => $this->values['category'],
            'disabled_children' => (int) $this->values['disabled_children'],
            'new_taxpayer' => (bool) $this->values['new_taxpayer'],
        ];
    }

    public function hasSalary(): bool
    {
        return (float) $this->values['monthly_salary'] > 0;
    }

    /** The salary profile as a package (annual totals), or null when no salary is saved. */
    public function package(): ?array
    {
        if (! $this->hasSalary()) {
            return null;
        }

        return SalaryPackage::annual([
            'monthly' => $this->values['monthly_salary'],
            'basic_pct' => $this->values['basic_pct'],
            'bonus_count' => $this->values['bonus_count'],
            'bonus_base' => $this->values['bonus_base'],
            'employer_pf' => $this->values['employer_pf'],
        ]);
    }

    /** Fields for an offer card, e.g. "Current job" in the offer comparer. */
    public function offer(string $name): ?array
    {
        if (! $this->hasSalary()) {
            return null;
        }

        return [
            'name' => $this->values['employer'] ?: $name,
            'monthly' => (float) $this->values['monthly_salary'],
            'basic_pct' => (float) $this->values['basic_pct'],
            'bonus_count' => (float) $this->values['bonus_count'],
            'bonus_base' => $this->values['bonus_base'],
            'other_annual' => 0,
            'employer_pf' => (float) ($this->values['employer_pf'] ?? 0),
        ];
    }

    /**
     * Cookies that carry the display settings to every page. Language is read
     * by the SetLocale middleware; digits and grouping are also read by JS.
     *
     * @return Cookie[]
     */
    public function displayCookies(): array
    {
        $cookies = [];
        foreach (['locale' => 'kh_locale', 'digits' => 'kh_digits', 'grouping' => 'kh_grouping'] as $key => $name) {
            if ($this->values[$key]) {
                $cookies[] = cookie()->forever($name, $this->values[$key], httpOnly: $key === 'locale');
            }
        }

        return $cookies;
    }

    /** What the browser should copy into localStorage after a sign-in or a settings change. */
    public function displaySync(): array
    {
        return array_filter(['digits' => $this->values['digits'], 'grouping' => $this->values['grouping']]);
    }
}
