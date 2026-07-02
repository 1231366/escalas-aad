<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\ShiftType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShiftType>
 */
class ShiftTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'code' => 'M',
            'name' => 'Manhã',
            'starts_at' => '08:00',
            'ends_at' => '16:00',
            'hours' => 8,
            'color' => '#f59e0b',
        ];
    }

    public function tarde(): static
    {
        return $this->state([
            'code' => 'T', 'name' => 'Tarde',
            'starts_at' => '16:00', 'ends_at' => '00:00', 'color' => '#3b82f6',
        ]);
    }

    public function noite(): static
    {
        return $this->state([
            'code' => 'N', 'name' => 'Noite',
            'starts_at' => '00:00', 'ends_at' => '08:00', 'color' => '#8b5cf6',
        ]);
    }
}
