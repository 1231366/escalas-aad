<?php

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class NotificationPrefsUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['sometimes', 'array'],
            'email.invitation_accepted' => ['sometimes', 'boolean'],
            'email.schedule_published' => ['sometimes', 'boolean'],
            'email.swap_request' => ['sometimes', 'boolean'],
            'email.swap_decided' => ['sometimes', 'boolean'],
            'email.vacation_decided' => ['sometimes', 'boolean'],
        ];
    }
}
