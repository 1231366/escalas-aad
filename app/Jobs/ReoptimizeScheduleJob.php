<?php

namespace App\Jobs;

use App\Enums\AssignmentOrigin;
use App\Models\Absence;
use App\Models\AuditLog;
use App\Models\Schedule;
use App\Models\ShiftAssignment;
use App\Models\ShiftType;
use App\Services\Solver\PartialReoptimizer;
use App\Services\Solver\SolverUnavailableException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Re-otimização parcial de uma escala PUBLISHED disparada pelo registo de
 * uma ausência (issue #18, PRD F6). Só o troço [corte, period_end] é pedido
 * ao solver — os dias antes do corte nunca são tocados — e as folgas de
 * férias aprovadas (origin VACATION) são preservadas tal como estão.
 *
 * Deliberadamente um job à parte de GenerateScheduleJob: aquele assume
 * sempre uma escala DRAFT e o período completo; este mexe numa escala já
 * PUBLISHED e só num troço dela.
 */
class ReoptimizeScheduleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public function __construct(
        public Schedule $schedule,
        public string $cutoffDate,
        public Absence $absence,
    ) {}

    public function handle(PartialReoptimizer $reoptimizer): void
    {
        $schedule = Schedule::query()->withoutGlobalScopes()->findOrFail($this->schedule->id);
        $absence = Absence::query()->withoutGlobalScopes()->findOrFail($this->absence->id);
        $cutoff = Carbon::parse($this->cutoffDate)->startOfDay();

        try {
            $result = $reoptimizer->reoptimize($schedule, $cutoff);
        } catch (SolverUnavailableException $e) {
            Log::error('Solver indisponível ao re-otimizar escala', [
                'schedule_id' => $schedule->id,
                'absence_id' => $absence->id,
                'error' => $e->getMessage(),
            ]);

            $absence->forceFill([
                'reoptimized_at' => now(),
                'reoptimization_status' => 'UNAVAILABLE',
                'reoptimization_conflicts' => [],
            ])->save();

            return;
        }

        $status = $result['status'] ?? 'INFEASIBLE';

        if ($status !== 'FEASIBLE') {
            $absence->forceFill([
                'reoptimized_at' => now(),
                'reoptimization_status' => $status,
                'reoptimization_conflicts' => $result['conflicts'] ?? [],
            ])->save();

            return;
        }

        $shiftTypesByCode = ShiftType::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $schedule->organization_id)
            ->get()
            ->keyBy('code');

        DB::transaction(function () use ($schedule, $absence, $cutoff, $result, $status, $shiftTypesByCode) {
            // Células com folga de férias já aprovada (origin VACATION) na
            // janela re-otimizada: preservam-se tal como estão — nem se
            // apagam nem se substituem pelo que o solver devolver para elas.
            $preserved = ShiftAssignment::query()
                ->where('schedule_id', $schedule->id)
                ->whereDate('date', '>=', $cutoff->toDateString())
                ->where('origin', AssignmentOrigin::Vacation->value)
                ->get()
                ->map(fn (ShiftAssignment $assignment) => $assignment->employee_id.'|'.$assignment->date->toDateString())
                ->all();

            ShiftAssignment::query()
                ->where('schedule_id', $schedule->id)
                ->whereDate('date', '>=', $cutoff->toDateString())
                ->where('origin', '!=', AssignmentOrigin::Vacation->value)
                ->delete();

            $now = now();

            $rows = collect($result['assignments'] ?? [])
                ->filter(fn (array $assignment) => ! in_array($assignment['employee_id'].'|'.$assignment['date'], $preserved, true))
                ->map(fn (array $assignment) => [
                    'schedule_id' => $schedule->id,
                    'employee_id' => $assignment['employee_id'],
                    'date' => $assignment['date'],
                    'shift_type_id' => $assignment['shift'] !== null
                        ? $shiftTypesByCode->get($assignment['shift'])?->id
                        : null,
                    'origin' => AssignmentOrigin::Generated->value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
                ->all();

            if ($rows !== []) {
                ShiftAssignment::query()->insert($rows);
            }

            $absence->forceFill([
                'reoptimized_at' => $now,
                'reoptimization_status' => $status,
                'reoptimization_conflicts' => null,
            ])->save();
        });

        AuditLog::record('absence.reoptimized', $schedule, [
            'absence_id' => $absence->id,
            'cutoff' => $cutoff->toDateString(),
            'status' => $status,
        ]);
    }
}
