<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateShiftTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i'],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'starts_at.date_format' => 'Hora de início inválida (formato HH:MM).',
            'ends_at.date_format' => 'Hora de fim inválida (formato HH:MM).',
            'color.regex' => 'Cor inválida — usa o formato hexadecimal (ex.: #f59e0b).',
        ];
    }
}
