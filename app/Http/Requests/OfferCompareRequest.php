<?php

namespace App\Http\Requests;

use App\Services\Tax\OfferComparer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OfferCompareRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $money = ['nullable', 'numeric', 'min:0', 'max:10000000000'];

        return [
            'year' => ['required', Rule::in(array_keys(config('tax.years')))],
            'category' => ['required', Rule::in(array_keys(config('tax.categories')))],
            'investment_mode' => ['required', Rule::in(OfferComparer::MODES)],
            'investment' => $money,
            'offers' => ['required', 'array', 'min:2', 'max:3'],
            'offers.*.name' => ['nullable', 'string', 'max:60'],
            'offers.*.monthly' => ['required', 'numeric', 'min:0', 'max:1000000000'],
            'offers.*.basic_pct' => ['nullable', 'numeric', 'min:1', 'max:100'],
            'offers.*.bonus_count' => ['nullable', 'numeric', 'min:0', 'max:12'],
            'offers.*.bonus_base' => ['nullable', Rule::in(OfferComparer::BONUS_BASES)],
            'offers.*.other_annual' => $money,
            'offers.*.employer_pf' => $money,
            'grouping' => ['nullable', Rule::in(['intl', 'lakh'])],
        ];
    }

    public function attributes(): array
    {
        return [
            'offers' => __('offers'),
            'offers.*.monthly' => __('monthly salary'),
            'offers.*.basic_pct' => __('basic share'),
            'offers.*.bonus_count' => __('number of bonuses'),
        ];
    }
}
