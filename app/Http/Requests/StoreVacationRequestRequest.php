<?php

namespace App\Http\Requests;

use App\Enums\VacationStatus;
use App\Models\VacationRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreVacationRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->employee !== null;
    }

    public function rules(): array
    {
        return [
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $employee = $this->user()->employee;

            // Uma pessoa não pode ter dois pedidos ativos a sobrepor datas —
            // PENDING e APPROVED contam como ativos, CANCELLED/DECLINED não.
            $overlaps = VacationRequest::query()
                ->where('employee_id', $employee->id)
                ->whereIn('status', [VacationStatus::Pending, VacationStatus::Approved])
                ->where('start_date', '<=', $this->input('end_date'))
                ->where('end_date', '>=', $this->input('start_date'))
                ->exists();

            if ($overlaps) {
                $validator->errors()->add('start_date', 'Já tens um pedido de férias pendente ou aprovado que sobrepõe este período.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'start_date.after_or_equal' => 'A data de início não pode ser no passado.',
            'end_date.after_or_equal' => 'A data de fim tem de ser igual ou posterior à data de início.',
        ];
    }
}
