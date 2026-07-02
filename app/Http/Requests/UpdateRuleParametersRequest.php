<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRuleParametersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'hour_bank_weekly_tolerance' => ['required', 'numeric', 'between:0,16'],
            'max_consecutive_work_days' => ['required', 'integer', 'between:1,6'],
            'ff_window_weeks' => ['required', 'integer', 'between:1,12'],
            'ff_monthly' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'hour_bank_weekly_tolerance.between' => 'A tolerância do banco de horas deve estar entre 0 e 16 horas/semana.',
            'max_consecutive_work_days.between' => 'O máximo de dias consecutivos deve estar entre 1 e 6 (limite legal).',
            'ff_window_weeks.between' => 'A janela de folgas seguidas deve estar entre 1 e 12 semanas.',
            'ff_monthly.required' => 'Indica se é exigida uma dupla folga (FF) por mês.',
        ];
    }

    /**
     * Normaliza os tipos antes de validar (o payload chega do formulário como strings).
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'hour_bank_weekly_tolerance' => is_numeric($this->hour_bank_weekly_tolerance)
                ? (float) $this->hour_bank_weekly_tolerance
                : $this->hour_bank_weekly_tolerance,
            'max_consecutive_work_days' => is_numeric($this->max_consecutive_work_days)
                ? (int) $this->max_consecutive_work_days
                : $this->max_consecutive_work_days,
            'ff_window_weeks' => is_numeric($this->ff_window_weeks)
                ? (int) $this->ff_window_weeks
                : $this->ff_window_weeks,
            'ff_monthly' => filter_var($this->ff_monthly, FILTER_VALIDATE_BOOLEAN),
        ]);
    }
}
