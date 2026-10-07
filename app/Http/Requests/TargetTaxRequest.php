<?php

namespace App\Http\Requests;

use App\Services\Tax\TargetTaxSolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TargetTaxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'target_tax' => ['required', 'numeric', 'min:0', 'max:1000000000'],
            'rebate_mode' => ['required', Rule::in(TargetTaxSolver::MODES)],
            'investment' => ['nullable', 'numeric', 'min:0', 'max:10000000000'],
            'year' => ['required', Rule::in(array_keys(config('tax.years')))],
            'category' => ['required', Rule::in(array_keys(config('tax.categories')))],
            'ratios' => ['nullable', 'array'],
            'grouping' => ['nullable', Rule::in(['intl', 'lakh'])],
        ];
        foreach (array_keys(config('tax.salary_components')) as $key) {
            $rules["ratios.{$key}"] = ['nullable', 'numeric', 'min:0', 'max:100'];
        }

        return $rules;
    }

    public function attributes(): array
    {
        return ['target_tax' => 'tax amount'];
    }
}
