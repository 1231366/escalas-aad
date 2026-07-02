<?php

namespace App\Services;

use App\Models\CoverageRule;
use App\Models\Employee;
use App\Models\Schedule;
use App\Models\ShiftAssignment;
use App\Models\ShiftType;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

/**
 * Monta a grelha pessoas × dias de uma escala com os indicadores da folha do
 * cliente (ADR-0004): horas/mês e média/semana, folgas e fins de semana
 * trabalhados por pessoa; cobertura efetiva vs exigida por dia.
 *
 * Partilhado entre a vista de admin (Admin\ScheduleController) e a vista
 * só-leitura da funcionária (MyScheduleController) para não duplicar a
 * lógica de leitura da grelha.
 */
class ScheduleGridBuilder
{
    /**
     * @return Collection<int, CarbonInterface>
     */
    public function dates(Schedule $schedule): Collection
    {
        return collect(CarbonPeriod::create($schedule->period_start, $schedule->period_end))->values();
    }

    /**
     * @param  Collection<int, Employee>  $employees
     * @param  Collection<int, CarbonInterface>  $dates
     * @return Collection<int, array<string, mixed>>
     */
    public function employeeRows(Schedule $schedule, Collection $employees, Collection $dates): Collection
    {
        $schedule->loadMissing(['assignments.shiftType']);

        $assignmentsByEmployee = $schedule->assignments
            ->groupBy('employee_id')
            ->map(fn (Collection $assignments) => $assignments->keyBy(fn (ShiftAssignment $a) => $a->date->toDateString()));

        $weeksInPeriod = $dates->count() > 0 ? $dates->count() / 7 : 0;

        return $employees->map(function (Employee $employee) use ($dates, $assignmentsByEmployee, $weeksInPeriod) {
            $employeeAssignments = $assignmentsByEmployee->get($employee->id, collect());

            $totalHours = 0.0;
            $daysOff = 0;
            $weekendsWorked = 0;
            $cells = [];

            foreach ($dates as $date) {
                /** @var ShiftAssignment|null $assignment */
                $assignment = $employeeAssignments->get($date->toDateString());

                if ($assignment === null) {
                    $cells[] = ['date' => $date->toDateString(), 'shift_code' => null, 'shift_type_id' => null, 'is_day_off' => false];

                    continue;
                }

                if ($assignment->isDayOff()) {
                    $daysOff++;
                    $cells[] = ['date' => $date->toDateString(), 'shift_code' => null, 'shift_type_id' => null, 'is_day_off' => true];

                    continue;
                }

                $shiftType = $assignment->shiftType;
                $totalHours += (float) ($shiftType->hours ?? 0);
                if ($date->isWeekend()) {
                    $weekendsWorked++;
                }

                $cells[] = [
                    'date' => $date->toDateString(),
                    'shift_code' => $shiftType->code,
                    'shift_type_id' => $shiftType->id,
                    'is_day_off' => false,
                ];
            }

            return [
                'employee_id' => $employee->id,
                'name' => $employee->name,
                'cells' => $cells,
                'total_hours' => $totalHours,
                'avg_weekly_hours' => $weeksInPeriod > 0 ? round($totalHours / $weeksInPeriod, 1) : 0.0,
                'days_off' => $daysOff,
                'weekends_worked' => $weekendsWorked,
            ];
        })->values();
    }

    /**
     * @param  Collection<int, ShiftType>  $shiftTypes
     * @param  Collection<int, CarbonInterface>  $dates
     * @return Collection<int, array<string, mixed>>
     */
    public function dayFooters(Schedule $schedule, Collection $shiftTypes, Collection $dates): Collection
    {
        $coverageRules = CoverageRule::query()->get();

        $countsByDateAndShift = $schedule->assignments
            ->whereNotNull('shift_type_id')
            ->groupBy(fn (ShiftAssignment $a) => $a->date->toDateString().'|'.$a->shift_type_id)
            ->map->count();

        return $dates->map(function ($date) use ($shiftTypes, $coverageRules, $countsByDateAndShift) {
            // dayOfWeekIso: 1=segunda…7=domingo → convenção do projeto é 0=segunda…6=domingo.
            $weekday = $date->dayOfWeekIso - 1;

            $shifts = $shiftTypes->map(function (ShiftType $shiftType) use ($date, $weekday, $coverageRules, $countsByDateAndShift) {
                $required = $coverageRules
                    ->first(fn (CoverageRule $rule) => $rule->shift_type_id === $shiftType->id && $rule->weekday === $weekday)
                    ?->required ?? 0;

                $actual = $countsByDateAndShift->get($date->toDateString().'|'.$shiftType->id, 0);

                return [
                    'shift_type_id' => $shiftType->id,
                    'code' => $shiftType->code,
                    'required' => $required,
                    'actual' => $actual,
                    'ok' => $actual >= $required,
                ];
            })->values();

            return [
                'date' => $date->toDateString(),
                'is_weekend' => $date->isWeekend(),
                'shifts' => $shifts,
            ];
        })->values();
    }
}
