<?php

namespace Database\Factories;

use App\Models\CoverageRule;
use App\Models\Organization;
use App\Models\ShiftType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CoverageRule>
 */
class CoverageRuleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'shift_type_id' => ShiftType::factory(),
            'weekday' => 0,
            'required' => 4,
        ];
    }
}
