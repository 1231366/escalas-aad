<?php

namespace Database\Factories;

use App\Enums\SwapStatus;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\Schedule;
use App\Models\ShiftAssignment;
use App\Models\SwapRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SwapRequest>
 */
class SwapRequestFactory extends Factory
{
    public function definition(): array
    {
        $organization = Organization::factory();
        $schedule = Schedule::factory()->for($organization);

        $requester = Employee::factory()->for($organization);
        $target = Employee::factory()->for($organization);

        return [
            'organization_id' => $organization,
            'schedule_id' => $schedule,
            'requester_employee_id' => $requester,
            'target_employee_id' => $target,
            'requester_assignment_id' => ShiftAssignment::factory()
                ->for($schedule)->for($requester, 'employee'),
            'target_assignment_id' => ShiftAssignment::factory()
                ->for($schedule)->for($target, 'employee'),
            'status' => SwapStatus::Pending,
            'admin_approval_required' => true,
        ];
    }
}
