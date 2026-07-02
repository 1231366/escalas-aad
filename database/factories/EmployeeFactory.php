<?php

namespace Database\Factories;

use App\Enums\ContractType;
use App\Enums\Regime;
use App\Models\Employee;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'user_id' => null,
            'name' => fake('pt_PT')->name(),
            'regime' => Regime::Hibrido,
            'contract' => ContractType::H40,
            'fixa_noite' => false,
            'active' => true,
        ];
    }

    public function fixaNoite(): static
    {
        return $this->state(['regime' => Regime::Noite, 'fixa_noite' => true]);
    }

    public function regime(Regime $regime): static
    {
        return $this->state(['regime' => $regime]);
    }
}
