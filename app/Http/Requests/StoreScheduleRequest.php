<?php

namespace App\Http\Requests;

use App\Models\Schedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

class StoreScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'year' => ['required', 'integer', 'between:2020,2100'],
            'month' => ['required', 'integer', 'between:1,12'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $start = Carbon::create((int) $this->year, (int) $this->month, 1)->startOfMonth();

            // unique(organization_id, period_start, period_end) já existe na BD;
            // validamos aqui também para dar um erro de formulário amigável.
            $exists = Schedule::query()->whereDate('period_start', $start->toDateString())->exists();

            if ($exists) {
                $validator->errors()->add('month', 'Já existe uma escala para este período.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'year.between' => 'Ano inválido.',
            'month.between' => 'Mês inválido.',
        ];
    }
}
