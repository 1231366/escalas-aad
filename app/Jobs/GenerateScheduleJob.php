<?php

namespace App\Jobs;

use App\Enums\AssignmentOrigin;
use App\Models\Schedule;
use App\Models\ShiftAssignment;
use App\Models\ShiftType;
use App\Services\Solver\SolverClient;
use App\Services\Solver\SolverUnavailableException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Chama o solver (POST /generate) para um Schedule DRAFT e persiste o
 * resultado (ADR-0002, PRD F4). Corre em fila — em produção sem utilizador
 * autenticado — por isso todas as queries são explícitas por organization_id
 * em vez de depender do global scope de tenant (ver SolverClient).
 */
class GenerateScheduleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public function __construct(public Schedule $schedule) {}

    public function handle(SolverClient $solver): void
    {
        $schedule = Schedule::query()->withoutGlobalScopes()->findOrFail($this->schedule->id);

        try {
            $result = $solver->generate($schedule);
        } catch (SolverUnavailableException $e) {
            Log::error('Solver indisponível ao gerar escala', [
                'schedule_id' => $schedule->id,
                'error' => $e->getMessage(),
            ]);

            $schedule->forceFill([
                'solver_stats' => [
                    'status' => 'UNAVAILABLE',
                    'conflicts' => [],
                    'error' => $e->getMessage(),
                ],
            ])->save();

            return;
        }

        $status = $result['status'] ?? 'INFEASIBLE';

        if ($status !== 'FEASIBLE') {
            $schedule->forceFill([
                'solver_stats' => [
                    'status' => $status,
                    'conflicts' => $result['conflicts'] ?? [],
                ],
            ])->save();

            return;
        }

        $shiftTypesByCode = ShiftType::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $schedule->organization_id)
            ->get()
            ->keyBy('code');

        DB::transaction(function () use ($schedule, $result, $status, $shiftTypesByCode) {
            ShiftAssignment::query()->where('schedule_id', $schedule->id)->delete();

            $now = now();

            $rows = collect($result['assignments'] ?? [])
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

            $schedule->forceFill([
                'solver_stats' => [
                    'status' => $status,
                    'objective' => $result['objective'] ?? null,
                    'wall_time_s' => $result['wall_time_s'] ?? null,
                ],
                'generated_at' => $now,
            ])->save();
        });
    }
}
