<?php

namespace App\Http\Requests;

class SaveCalculationRequest extends CalculationInputRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            'title' => ['required', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
