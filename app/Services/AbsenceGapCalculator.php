<?php

namespace App\Services;

use App\Enums\ScheduleStatus;
use App\Models\Absence;
use App\Models\CoverageRule;
use App\Models\Schedule;
use App\Models\ShiftAssignment;

/**
 * Calcula, em PHP, os buracos de cobertura criados por uma ausência recém
 * registada (issue #18). Não chama o solver: compara os turnos que a pessoa
 * já tinha atribuídos (numa escala PUBLISHED que cubra o intervalo) com
 * coverage_rules, exatamente como o rodapé da grelha (ScheduleGridBuilder)
 * já faz para a escala inteira — aqui é só para os dias/turnos da pessoa
 * ausente.
 */
class AbsenceGapCalculator
{
    /**
     * @return array{schedule_id: ?int, gaps: list<array{date: string, shift_code: string, before: int, after: int, required: int}>}
     */
    public function calculate(Absence $absence): array
    {
        $organizationId = $absence->organization_id;

        $schedule = Schedule::query()
            ->where('organization_id', $organizationId)
            ->where('status', ScheduleStatus::Published)
            ->where('period_start', '<=', $absence->end_date)
            ->where('period_end', '>=', $absence->start_date)
            ->first();

        if (! $schedule) {
            return ['schedule_id' => null, 'gaps' => []];
        }

        $from = $absence->start_date->greaterThan($schedule->period_start) ? $absence->start_date : $schedule->period_start;
        $to = $absence->end_date->lessThan($schedule->period_end) ? $absence->end_date : $schedule->period_end;

        $employeeAssignments = ShiftAssignment::query()
            ->where('schedule_id', $schedule->id)
            ->where('employee_id', $absence->employee_id)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->whereNotNull('shift_type_id')
            ->with('shiftType')
            ->get();

        if ($employeeAssignments->isEmpty()) {
            return ['schedule_id' => $schedule->id, 'gaps' => []];
        }

        $coverageRules = CoverageRule::query()->where('organization_id', $organizationId)->get();

        // contagem atual por dia+turno na escala inteira, para saber quantas
        // pessoas ficam a cobrir o turno depois de tirar a ausente.
        $countsByDateAndShift = ShiftAssignment::query()
            ->where('schedule_id', $schedule->id)
            ->whereNotNull('shift_type_id')
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->groupBy(fn (ShiftAssignment $a) => $a->date->toDateString().'|'.$a->shift_type_id)
            ->map->count();

        $gaps = $employeeAssignments
            ->map(function (ShiftAssignment $assignment) use ($coverageRules, $countsByDateAndShift) {
                $weekday = $assignment->date->dayOfWeekIso - 1; // 0=segunda…6=domingo (convenção do projeto)
                $shiftType = $assignment->shiftType;

                $required = $coverageRules
                    ->first(fn (CoverageRule $rule) => $rule->shift_type_id === $shiftType->id && $rule->weekday === $weekday)
                    ?->required ?? 0;

                $before = $countsByDateAndShift->get($assignment->date->toDateString().'|'.$shiftType->id, 0);
                $after = max(0, $before - 1);

                return [
                    'date' => $assignment->date->toDateString(),
                    'shift_code' => $shiftType->code,
                    'before' => $before,
                    'after' => $after,
                    'required' => $required,
                ];
            })
            ->filter(fn (array $gap) => $gap['after'] < $gap['required'])
            ->sortBy('date')
            ->values()
            ->all();

        return ['schedule_id' => $schedule->id, 'gaps' => $gaps];
    }
}
