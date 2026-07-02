<?php

namespace Database\Factories;

use App\Enums\AssignmentOrigin;
use App\Models\Employee;
use App\Models\Schedule;
use App\Models\ShiftAssignment;
use App\Models\ShiftType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShiftAssignment>
 */
class ShiftAssignmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'schedule_id' => Schedule::factory(),
            'employee_id' => Employee::factory(),
            'date' => now()->addMonth()->startOfMonth()->toDateString(),
            'shift_type_id' => ShiftType::factory(),
            'origin' => AssignmentOrigin::Generated,
        ];
    }

    public function dayOff(): static
    {
        return $this->state(['shift_type_id' => null]);
    }
}
