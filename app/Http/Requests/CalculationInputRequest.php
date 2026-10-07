<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CalculationInputRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $money = ['nullable', 'numeric', 'min:0', 'max:10000000000'];

        $rules = [
            'year' => ['required', Rule::in(array_keys(config('tax.years')))],
            'category' => ['required', Rule::in(array_keys(config('tax.categories')))],
            'gross_income' => ['required', 'numeric', 'min:0', 'max:10000000000'],
            'investments' => ['nullable', 'array'],
            'tds_paid' => $money,
            'filing' => ['nullable', Rule::in(array_keys(config('tax.filing_periods')))],
            'new_taxpayer' => ['nullable', 'boolean'],
            'disabled_children' => ['nullable', 'integer', 'min:0', 'max:10'],
            'grouping' => ['nullable', Rule::in(['intl', 'lakh'])],
        ];

        foreach (array_keys(config('tax.instruments')) as $key) {
            $rules["investments.{$key}"] = $money;
        }

        return $rules;
    }

    public function attributes(): array
    {
        return [
            'gross_income' => 'gross income',
            'tds_paid' => 'tax already paid',
            'disabled_children' => 'number of disabled children',
        ];
    }

    /** Only the fields the tax engine understands. */
    public function taxInput(): array
    {
        return collect($this->validated())->except(['grouping', 'title', 'notes'])->all();
    }
}
