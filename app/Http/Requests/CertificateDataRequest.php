<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** The figures a user confirms after reviewing a salary certificate. */
class CertificateDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $money = ['nullable', 'numeric', 'min:0', 'max:10000000000'];
        $groups = [
            'components' => array_keys(config('salary_certificate.components')),
            'perks' => array_keys(config('salary.perks')),
            'investments' => array_keys(config('tax.instruments')),
        ];

        $rules = [
            'year' => ['required', Rule::in(array_keys(config('tax.years')))],
            'category' => ['required', Rule::in(array_keys(config('tax.categories')))],
            'disabled_children' => ['nullable', 'integer', 'min:0', 'max:10'],
            'new_taxpayer' => ['nullable', 'boolean'],
            'filing' => ['nullable', Rule::in(array_keys(config('tax.filing_periods')))],
            'employer' => ['nullable', 'string', 'max:120'],
            'employee' => ['nullable', 'string', 'max:120'],
            'tin' => ['nullable', 'string', 'regex:/^[0-9 \-]{6,20}$/'],
            'tds' => $money,
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
        foreach ($groups as $group => $keys) {
            $rules[$group] = ['required', 'array:'.implode(',', $keys)];
            $rules["{$group}.*"] = $money;
        }

        return $rules;
    }

    public function attributes(): array
    {
        return ['tds' => __('tax deducted'), 'tin' => __('TIN')];
    }
}
