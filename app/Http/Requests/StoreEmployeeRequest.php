<?php

namespace App\Http\Requests;

use App\Enums\ContractType;
use App\Enums\Regime;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'regime' => ['required', Rule::enum(Regime::class)],
            'contract' => ['required', Rule::enum(ContractType::class)],
            'fixa_noite' => ['boolean'],
        ];
    }
}
