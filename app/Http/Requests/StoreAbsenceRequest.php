<?php

namespace App\Http\Requests;

use App\Enums\AbsenceType;
use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAbsenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    public function rules(): array
    {
        $orgId = $this->user()->organization_id;

        return [
            'employee_id' => [
                'required',
                Rule::exists(Employee::class, 'id')->where(fn ($query) => $query->where('organization_id', $orgId)),
            ],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'type' => ['required', Rule::enum(AbsenceType::class)],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
