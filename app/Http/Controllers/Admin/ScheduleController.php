<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AssignmentOrigin;
use App\Enums\ScheduleStatus;
use App\Events\SchedulePublished;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreScheduleRequest;
use App\Http\Requests\UpdateScheduleCellRequest;
use App\Jobs\GenerateScheduleJob;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Schedule;
use App\Models\ShiftAssignment;
use App\Models\ShiftType;
use App\Services\ScheduleGridBuilder;
use App\Services\Solver\SolverClient;
use App\Services\Solver\SolverUnavailableException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Geração, revisão e publicação da escala mensal (PRD F4, ADR-0002).
 * Toda a lógica de regras vive no solver — este controller só orquestra o
 * Job, persiste o resultado e apresenta a grelha.
 */
class ScheduleController extends Controller
{
    private const WEEKDAY_ABBR = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];

    public function index(): Response
    {
        $schedules = Schedule::query()
            ->orderByDesc('period_start')
            ->get()
            ->map(fn (Schedule $schedule) => [
                'id' => $schedule->id,
                'period_start' => $schedule->period_start->toDateString(),
                'period_end' => $schedule->period_end->toDateString(),
                'label' => ucfirst($schedule->period_start->translatedFormat('F Y')),
                'status' => $schedule->status->value,
                'generated_at' => $schedule->generated_at?->toIso8601String(),
                'published_at' => $schedule->published_at?->toIso8601String(),
            ])
            ->values();

        return Inertia::render('admin/schedules/index', [
            'schedules' => $schedules,
        ]);
    }

    public function store(StoreScheduleRequest $request): RedirectResponse
    {
        $start = Carbon::create((int) $request->validated('year'), (int) $request->validated('month'), 1)->startOfMonth();

        $schedule = Schedule::create([
            'period_start' => $start->toDateString(),
            'period_end' => $start->copy()->endOfMonth()->toDateString(),
            'status' => ScheduleStatus::Draft,
            'generated_by' => $request->user()->id,
        ]);

        GenerateScheduleJob::dispatch($schedule);

        AuditLog::record('schedule.created', $schedule, ['period_start' => $schedule->period_start->toDateString()]);

        return redirect()->route('admin.schedules.show', $schedule)->with('success', 'Escala criada. A gerar…');
    }

    public function show(Schedule $schedule, ScheduleGridBuilder $grid): Response
    {
        $dates = $grid->dates($schedule);
        $employees = Employee::query()->active()->orderBy('name')->get();
        $shiftTypes = ShiftType::query()->orderedByShift()->get();

        return Inertia::render('admin/schedules/show', [
            'schedule' => [
                'id' => $schedule->id,
                'period_start' => $schedule->period_start->toDateString(),
                'period_end' => $schedule->period_end->toDateString(),
                'status' => $schedule->status->value,
                'generated_at' => $schedule->generated_at?->toIso8601String(),
                'published_at' => $schedule->published_at?->toIso8601String(),
                'solver_stats' => $schedule->solver_stats,
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
            ])->values(),
            'employees' => $grid->employeeRows($schedule, $employees, $dates),
            'day_footers' => $grid->dayFooters($schedule, $shiftTypes, $dates),
            'cell_violations' => session('cell_violations'),
            'cell_error' => session('cell_error'),
        ]);
    }

    public function regenerate(Schedule $schedule): RedirectResponse
    {
        abort_unless($schedule->isDraft(), 400, 'Só é possível gerar/regenerar uma escala em rascunho.');

        GenerateScheduleJob::dispatch($schedule);

        AuditLog::record('schedule.regenerated', $schedule);

        return back()->with('success', 'A gerar a escala…');
    }

    public function publish(Schedule $schedule): RedirectResponse
    {
        abort_unless($schedule->isDraft(), 400, 'Só é possível publicar uma escala em rascunho.');

        $schedule->forceFill([
            'status' => ScheduleStatus::Published,
            'published_at' => now(),
        ])->save();

        event(new SchedulePublished($schedule));

        AuditLog::record('schedule.published', $schedule);

        return back()->with('success', 'Escala publicada. A equipa foi notificada.');
    }

    public function archive(Schedule $schedule): RedirectResponse
    {
        abort_unless($schedule->isPublished(), 400, 'Só é possível arquivar uma escala publicada.');

        $schedule->update(['status' => ScheduleStatus::Archived]);

        AuditLog::record('schedule.archived', $schedule);

        return back()->with('success', 'Escala arquivada.');
    }

    /**
     * Edição manual de uma célula da grelha (issue #12). Monta a escala
     * hipotética completa (todas as atribuições atuais + a alteração) e pede
     * ao solver para a revalidar (ADR-0002) antes de persistir — nunca
     * reimplementamos H1–H10 aqui. Só permitido em DRAFT.
     */
    public function updateCell(UpdateScheduleCellRequest $request, Schedule $schedule, SolverClient $solver): RedirectResponse
    {
        abort_unless($schedule->isDraft(), 400, 'Só é possível editar células de uma escala em rascunho.');

        $data = $request->validated();

        abort_if(
            $data['date'] < $schedule->period_start->toDateString() || $data['date'] > $schedule->period_end->toDateString(),
            422,
            'A data está fora do período da escala.'
        );

        $employee = Employee::query()->findOrFail($data['employee_id']);
        $shiftType = $data['shift_type_id'] !== null
            ? ShiftType::query()->findOrFail($data['shift_type_id'])
            : null;

        $schedule->loadMissing(['assignments.shiftType']);

        $assignments = $schedule->assignments
            ->mapWithKeys(fn (ShiftAssignment $assignment) => [
                $assignment->employee_id.'|'.$assignment->date->toDateString() => [
                    'employee_id' => $assignment->employee_id,
                    'date' => $assignment->date->toDateString(),
                    'shift' => $assignment->shiftType?->code,
                ],
            ]);

        $assignments->put($employee->id.'|'.$data['date'], [
            'employee_id' => $employee->id,
            'date' => $data['date'],
            'shift' => $shiftType?->code,
        ]);

        try {
            $result = $solver->validate($schedule, $assignments->values()->all());
        } catch (SolverUnavailableException) {
            return back()->with('cell_error', 'Validação indisponível — solver offline.');
        }

        if (! ($result['valid'] ?? false)) {
            return back()->with('cell_violations', $result['violations'] ?? []);
        }

        // whereDate() em vez de igualdade direta: a coluna é DATE mas o valor
        // gravado pelo cast Eloquent inclui a hora (00:00:00), enquanto o
        // Job de geração insere a data "nua" — a comparação tem de aguentar
        // as duas representações (cf. GenerateScheduleJob).
        $assignment = ShiftAssignment::query()
            ->where('schedule_id', $schedule->id)
            ->where('employee_id', $employee->id)
            ->whereDate('date', $data['date'])
            ->first();

        if ($assignment) {
            $assignment->forceFill(['shift_type_id' => $shiftType?->id, 'origin' => AssignmentOrigin::Manual])->save();
        } else {
            ShiftAssignment::create([
                'schedule_id' => $schedule->id,
                'employee_id' => $employee->id,
                'date' => $data['date'],
                'shift_type_id' => $shiftType?->id,
                'origin' => AssignmentOrigin::Manual,
            ]);
        }

        AuditLog::record('schedule.cell.updated', $schedule, [
            'employee_id' => $employee->id,
            'date' => $data['date'],
            'shift_type_id' => $shiftType?->id,
        ]);

        return back()->with('success', 'Célula atualizada.');
    }
}
