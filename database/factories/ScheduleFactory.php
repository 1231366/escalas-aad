<?php

namespace Database\Factories;

use App\Enums\ScheduleStatus;
use App\Models\Organization;
use App\Models\Schedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Schedule>
 */
class ScheduleFactory extends Factory
{
    public function definition(): array
    {
        $start = now()->addMonth()->startOfMonth();

        return [
            'organization_id' => Organization::factory(),
            'period_start' => $start->toDateString(),
            'period_end' => $start->copy()->endOfMonth()->toDateString(),
            'status' => ScheduleStatus::Draft,
        ];
    }

    public function published(): static
    {
        return $this->state([
            'status' => ScheduleStatus::Published,
            'published_at' => now(),
        ]);
    }
}
