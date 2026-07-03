<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSwapRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->employee !== null;
    }

    public function rules(): array
    {
        return [
            'requester_assignment_id' => ['required', 'integer'],
            'target_employee_id' => ['required', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'requester_assignment_id.required' => 'Turno em falta.',
            'target_employee_id.required' => 'Colega em falta.',
        ];
    }
}
