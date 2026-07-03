<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAbsenceRequest;
use App\Jobs\ReoptimizeScheduleJob;
use App\Models\Absence;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Schedule;
use App\Services\AbsenceGapCalculator;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Registo de ausências (baixa/falta) pelo admin, aviso dos buracos de
 * cobertura que criam numa escala já PUBLISHED, e pedido de re-otimização
 * parcial do troço afetado (issue #18, PRD F6).
 */
class AbsenceController extends Controller
{
    public function index(): Response
    {
        $employees = Employee::query()->active()->orderBy('name')->get(['id', 'name']);

        $absences = Absence::query()
            ->with('employee')
            ->orderByDesc('start_date')
            ->get()
            ->map(function (Absence $absence) {
                $schedule = $absence->schedule_id ? Schedule::query()->find($absence->schedule_id) : null;
                $cutoff = $schedule ? $absence->reoptimizationCutoff($schedule) : null;

                return [
                    'id' => $absence->id,
                    'employee_id' => $absence->employee_id,
                    'employee_name' => $absence->employee->name,
                    'start_date' => $absence->start_date->toDateString(),
                    'end_date' => $absence->end_date->toDateString(),
                    'type' => $absence->type->value,
                    'type_label' => $absence->type->label(),
                    'note' => $absence->note,
                    'coverage_gaps' => $absence->coverage_gaps ?? [],
                    'schedule_id' => $absence->schedule_id,
                    'reoptimizable_from' => $cutoff?->toDateString(),
                    'reoptimized_at' => $absence->reoptimized_at?->toIso8601String(),
                    'reoptimization_status' => $absence->reoptimization_status,
                    'reoptimization_conflicts' => $absence->reoptimization_conflicts,
                ];
            })
            ->values();

        return Inertia::render('admin/absences/index', [
            'employees' => $employees,
            'absences' => $absences,
        ]);
    }

    public function store(StoreAbsenceRequest $request, AbsenceGapCalculator $gapCalculator): RedirectResponse
    {
        $absence = Absence::create($request->validated());

        $result = $gapCalculator->calculate($absence);

        $absence->forceFill([
            'schedule_id' => $result['schedule_id'],
            'coverage_gaps' => $result['gaps'],
        ])->save();

        AuditLog::record('absence.created', $absence, [
            'employee_id' => $absence->employee_id,
            'type' => $absence->type->value,
            'start_date' => $absence->start_date->toDateString(),
            'end_date' => $absence->end_date->toDateString(),
            'coverage_gaps' => $result['gaps'],
        ]);

        return back()->with('success', 'Ausência registada.');
    }

    public function destroy(Absence $absence): RedirectResponse
    {
        AuditLog::record('absence.deleted', $absence, ['employee_id' => $absence->employee_id]);

        $absence->delete();

        return back()->with('success', 'Ausência removida.');
    }

    public function reoptimize(Absence $absence): RedirectResponse
    {
        abort_unless($absence->schedule_id, 400, 'Esta ausência não afeta nenhuma escala publicada.');

        $schedule = Schedule::query()->findOrFail($absence->schedule_id);

        $cutoff = $absence->reoptimizationCutoff($schedule);

        abort_unless($cutoff !== null, 400, 'Já não é possível re-otimizar esta escala (fora do período ou já não está publicada).');

        ReoptimizeScheduleJob::dispatch($schedule, $cutoff->toDateString(), $absence);

        AuditLog::record('absence.reoptimize.requested', $absence, [
            'schedule_id' => $schedule->id,
            'cutoff' => $cutoff->toDateString(),
        ]);

        return back()->with('success', "A re-otimizar a escala a partir de {$cutoff->toDateString()}…");
    }
}
