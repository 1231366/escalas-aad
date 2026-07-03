<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateScheduleCellRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer'],
            'date' => ['required', 'date_format:Y-m-d'],
            'shift_type_id' => ['nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'employee_id.required' => 'Funcionária em falta.',
            'date.required' => 'Data em falta.',
            'date.date_format' => 'Data inválida.',
        ];
    }
}
