<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Enums\VacationStatus;
use App\Http\Requests\StoreVacationRequestRequest;
use App\Models\User;
use App\Models\VacationRequest;
use App\Notifications\VacationRequested;
use App\Services\Solver\SolverClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pedidos de férias da funcionária (PRD F6): antes de o admin decidir, o
 * solver testa se a cobertura aguenta a ausência (SolverClient::vacationImpact)
 * e essa análise fica guardada no pedido para orientar a decisão.
 */
class VacationController extends Controller
{
    public function index(Request $request): Response
    {
        $employee = $request->user()->employee;

        $vacations = VacationRequest::query()
            ->where('employee_id', $employee?->id)
            ->orderByDesc('start_date')
            ->get()
            ->map(fn (VacationRequest $vacation) => [
                'id' => $vacation->id,
                'start_date' => $vacation->start_date->toDateString(),
                'end_date' => $vacation->end_date->toDateString(),
                'status' => $vacation->status->value,
                'note' => $vacation->note,
                'decided_at' => $vacation->decided_at?->toIso8601String(),
                'created_at' => $vacation->created_at->toIso8601String(),
            ])
            ->values();

        return Inertia::render('vacations/index', [
            'vacations' => $vacations,
        ]);
    }

    public function store(StoreVacationRequestRequest $request, SolverClient $solver): RedirectResponse
    {
        $employee = $request->user()->employee;

        $impact = $solver->vacationImpact(
            $employee,
            $request->validated('start_date'),
            $request->validated('end_date'),
        );

        $vacation = VacationRequest::create([
            'employee_id' => $employee->id,
            'start_date' => $request->validated('start_date'),
            'end_date' => $request->validated('end_date'),
            'status' => VacationStatus::Pending,
            'impact' => $impact,
            'note' => $request->validated('note'),
        ]);

        $admins = User::query()
            ->where('organization_id', $request->user()->organization_id)
            ->where('role', Role::Admin)
            ->get();

        if ($admins->isNotEmpty()) {
            Notification::send($admins, new VacationRequested($vacation));
        }

        return back()->with('success', 'Pedido de férias enviado.');
    }

    public function cancel(Request $request, VacationRequest $vacationRequest): RedirectResponse
    {
        $employee = $request->user()->employee;

        abort_unless($employee && $vacationRequest->employee_id === $employee->id, 403);
        abort_unless($vacationRequest->status === VacationStatus::Pending, 400, 'Só é possível cancelar um pedido pendente.');

        $vacationRequest->update(['status' => VacationStatus::Cancelled]);

        return back()->with('success', 'Pedido de férias cancelado.');
    }
}
