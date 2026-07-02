<?php

namespace Database\Factories;

use App\Enums\ContractType;
use App\Enums\Regime;
use App\Enums\Role;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invitation>
 */
class InvitationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'email' => fake()->unique()->safeEmail(),
            'name' => fake('pt_PT')->firstName(),
            'role' => Role::Employee,
            'regime' => Regime::Hibrido,
            'contract' => ContractType::H40,
            'fixa_noite' => false,
            'token' => Invitation::generateToken(),
            'expires_at' => now()->addDays(7),
            'created_by' => User::factory(),
        ];
    }

    public function expired(): static
    {
        return $this->state(['expires_at' => now()->subDay()]);
    }

    public function revoked(): static
    {
        return $this->state(['revoked_at' => now()]);
    }
}
