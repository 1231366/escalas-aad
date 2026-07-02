<?php

namespace App\Http\Requests;

use App\Enums\ContractType;
use App\Enums\Regime;
use App\Enums\Role;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    public function rules(): array
    {
        $orgId = $this->user()->organization_id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'email', 'max:255',
                // sem conta nem convite pendente duplicado na org
                Rule::unique(User::class, 'email'),
                Rule::unique(Invitation::class, 'email')->where(
                    fn ($query) => $query
                        ->where('organization_id', $orgId)
                        ->whereNull('accepted_at')
                        ->whereNull('revoked_at')
                        ->where('expires_at', '>', now())
                ),
            ],
            'role' => ['required', Rule::enum(Role::class)],
            'regime' => ['required', Rule::enum(Regime::class)],
            'contract' => ['required', Rule::enum(ContractType::class)],
            'fixa_noite' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'Já existe uma conta ou um convite pendente com este email.',
        ];
    }
}
