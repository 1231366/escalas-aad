<?php

namespace App\Http\Controllers;

use App\Enums\ScheduleStatus;
use App\Models\Employee;
use App\Models\Schedule;
use App\Models\ShiftType;
use App\Services\ScheduleGridBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Consulta da escala pela funcionária (PRD F4): a escala PUBLISHED do mês
 * corrente ou, se ainda não existir, a próxima publicada. Grelha só-leitura
 * de toda a equipa, com os turnos da própria funcionária realçados.
 */
class MyScheduleController extends Controller
{
    private const WEEKDAY_ABBR = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];

    public function show(Request $request, ScheduleGridBuilder $grid): Response
    {
        $employee = $request->user()->employee;

        $today = Carbon::today()->toDateString();

        $schedule = Schedule::query()
            ->where('status', ScheduleStatus::Published)
            ->where('period_start', '<=', $today)
            ->where('period_end', '>=', $today)
            ->first();

        if (! $schedule) {
            $schedule = Schedule::query()
                ->where('status', ScheduleStatus::Published)
                ->where('period_start', '>', $today)
                ->orderBy('period_start')
                ->first();
        }

        if (! $schedule) {
            return Inertia::render('schedule/my', [
                'schedule' => null,
                'shift_types' => [],
                'dates' => [],
                'employees' => [],
                'my_employee_id' => $employee?->id,
            ]);
        }

        $dates = $grid->dates($schedule);
        $employees = Employee::query()->active()->orderBy('name')->get();
        $shiftTypes = ShiftType::query()->orderedByShift()->get();

        $weekStart = Carbon::today()->startOfWeek()->toDateString();
        $weekEnd = Carbon::today()->endOfWeek()->toDateString();

        return Inertia::render('schedule/my', [
            'schedule' => [
                'id' => $schedule->id,
                'period_start' => $schedule->period_start->toDateString(),
                'period_end' => $schedule->period_end->toDateString(),
                'status' => $schedule->status->value,
                'published_at' => $schedule->published_at?->toIso8601String(),
            ],
            'shift_types' => $shiftTypes->map(fn (ShiftType $shiftType) => [
                'id' => $shiftType->id,
                'code' => $shiftType->code,
                'name' => $shiftType->name,
                'color' => $shiftType->color,
            ])->values(),
            'dates' => $dates->map(fn ($date) => [
                'date' => $date->toDateString(),
                'day' => $date->day,
                'weekday_label' => self::WEEKDAY_ABBR[$date->dayOfWeek],
                'is_weekend' => $date->isWeekend(),
                'is_current_week' => $date->toDateString() >= $weekStart && $date->toDateString() <= $weekEnd,
            ])->values(),
            'employees' => $grid->employeeRows($schedule, $employees, $dates)
                ->map(fn (array $row) => [...$row, 'is_self' => $employee !== null && $row['employee_id'] === $employee->id])
                ->values(),
            'my_employee_id' => $employee?->id,
        ]);
    }
}
