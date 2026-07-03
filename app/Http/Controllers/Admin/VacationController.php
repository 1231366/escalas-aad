<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AssignmentOrigin;
use App\Enums\ScheduleStatus;
use App\Enums\VacationStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Schedule;
use App\Models\ShiftAssignment;
use App\Models\VacationRequest;
use App\Notifications\VacationDecided;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Decisão do admin sobre pedidos de férias (PRD F6). O impacto na cobertura
 * já foi calculado pelo solver no momento do pedido (VacationController::store,
 * SolverClient::vacationImpact) e é apresentado aqui — a decisão em si (mesmo
 * havendo impacto) é sempre do admin, o solver só informa.
 */
class VacationController extends Controller
{
    public function index(): Response
    {
        $vacations = VacationRequest::query()
            ->with(['employee', 'decider'])
            ->orderByDesc('start_date')
            ->get()
            ->map(fn (VacationRequest $vacation) => [
                'id' => $vacation->id,
                'employee_id' => $vacation->employee_id,
                'employee_name' => $vacation->employee->name,
                'start_date' => $vacation->start_date->toDateString(),
                'end_date' => $vacation->end_date->toDateString(),
                'status' => $vacation->status->value,
                'note' => $vacation->note,
                'impact' => $vacation->impact,
                'decided_by_name' => $vacation->decider?->name,
                'decided_at' => $vacation->decided_at?->toIso8601String(),
                'created_at' => $vacation->created_at->toIso8601String(),
            ])
            ->values();

        return Inertia::render('admin/vacations/index', [
            'vacations' => $vacations,
        ]);
    }

    public function approve(Request $request, VacationRequest $vacationRequest): RedirectResponse
    {
        abort_unless($vacationRequest->status === VacationStatus::Pending, 400, 'Só é possível decidir um pedido pendente.');

        DB::transaction(function () use ($vacationRequest, $request) {
            $vacationRequest->forceFill([
                'status' => VacationStatus::Approved,
                'decided_by' => $request->user()->id,
                'decided_at' => now(),
            ])->save();

            // Se já houver escala(s) PUBLISHED a cobrir (parte do) período de
            // férias, os dias da pessoa nesse intervalo passam a folga com
            // origem VACATION — não apagamos nada, só marcamos a folga.
            $scheduleIds = Schedule::query()
                ->where('status', ScheduleStatus::Published)
                ->where('period_start', '<=', $vacationRequest->end_date)
                ->where('period_end', '>=', $vacationRequest->start_date)
                ->pluck('id');

            if ($scheduleIds->isNotEmpty()) {
                ShiftAssignment::query()
                    ->whereIn('schedule_id', $scheduleIds)
                    ->where('employee_id', $vacationRequest->employee_id)
                    ->whereDate('date', '>=', $vacationRequest->start_date)
                    ->whereDate('date', '<=', $vacationRequest->end_date)
                    ->update(['shift_type_id' => null, 'origin' => AssignmentOrigin::Vacation]);
            }
        });

        AuditLog::record('vacation.approved', $vacationRequest, [
            'employee_id' => $vacationRequest->employee_id,
            'start_date' => $vacationRequest->start_date->toDateString(),
            'end_date' => $vacationRequest->end_date->toDateString(),
        ]);

        $vacationRequest->employee->user?->notify(new VacationDecided($vacationRequest));

        return back()->with('success', 'Pedido de férias aprovado.');
    }

    public function decline(Request $request, VacationRequest $vacationRequest): RedirectResponse
    {
        abort_unless($vacationRequest->status === VacationStatus::Pending, 400, 'Só é possível decidir um pedido pendente.');

        $vacationRequest->forceFill([
            'status' => VacationStatus::Declined,
            'decided_by' => $request->user()->id,
            'decided_at' => now(),
        ])->save();

        AuditLog::record('vacation.declined', $vacationRequest, [
            'employee_id' => $vacationRequest->employee_id,
        ]);

        $vacationRequest->employee->user?->notify(new VacationDecided($vacationRequest));

        return back()->with('success', 'Pedido de férias recusado.');
    }
}
