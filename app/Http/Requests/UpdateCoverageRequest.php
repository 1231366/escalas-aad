<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCoverageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        $orgId = $this->user()->organization_id;

        return [
            'coverage' => ['required', 'array', 'min:1'],
            'coverage.*.shift_type_id' => [
                'required',
                'integer',
                Rule::exists('shift_types', 'id')->where(fn ($query) => $query->where('organization_id', $orgId)),
            ],
            'coverage.*.weekday' => ['required', 'integer', 'between:0,6'],
            'coverage.*.required' => ['required', 'integer', 'between:0,20'],
        ];
    }

    public function messages(): array
    {
        return [
            'coverage.required' => 'É necessário indicar a cobertura.',
            'coverage.*.shift_type_id.exists' => 'Turno inválido.',
            'coverage.*.weekday.between' => 'Dia da semana inválido (deve ser entre 0 e 6).',
            'coverage.*.required.between' => 'A cobertura exigida deve estar entre 0 e 20.',
        ];
    }
}
