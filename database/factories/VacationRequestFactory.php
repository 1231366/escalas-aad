<?php

namespace Database\Factories;

use App\Enums\VacationStatus;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\VacationRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VacationRequest>
 */
class VacationRequestFactory extends Factory
{
    public function definition(): array
    {
        $start = now()->addMonths(2)->startOfWeek();

        return [
            'organization_id' => Organization::factory(),
            'employee_id' => Employee::factory(),
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->addDays(6)->toDateString(),
            'status' => VacationStatus::Pending,
        ];
    }
}
