<?php

namespace App\Http\Requests;

use App\Services\Tax\TdsPlanner;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TdsPlanRequest extends FormRequest
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
            'new_taxpayer' => ['nullable', 'boolean'],
            'disabled_children' => ['nullable', 'integer', 'min:0', 'max:10'],
            'investments' => ['nullable', 'array:'.implode(',', array_keys(config('tax.instruments')))],
            'investments.*' => $money,
            'perks' => ['nullable', 'array:'.implode(',', array_keys(config('salary.perks')))],
            'perks.*' => $money,
            'strategy' => ['nullable', Rule::in(TdsPlanner::STRATEGIES)],
            'monthly_cap' => $money,
            'months' => ['required', 'array:'.implode(',', TdsPlanner::MONTHS)],
            'grouping' => ['nullable', Rule::in(['intl', 'lakh'])],
        ];
        foreach (TdsPlanner::MONTHS as $month) {
            $rules["months.{$month}"] = ['required', 'array'];
            $rules["months.{$month}.salary"] = $money;
            $rules["months.{$month}.bonus"] = $money;
            $rules["months.{$month}.tds"] = $money;
            $rules["months.{$month}.done"] = ['nullable', 'boolean'];
        }

        return $rules;
    }

    public function attributes(): array
    {
        return ['monthly_cap' => __('monthly TDS limit')];
    }
}
