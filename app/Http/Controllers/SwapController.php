<?php

namespace App\Http\Controllers;

use App\Enums\SwapStatus;
use App\Http\Requests\StoreSwapRequest;
use App\Models\Employee;
use App\Models\ShiftAssignment;
use App\Models\SwapRequest;
use App\Services\Solver\SolverClient;
use App\Services\Swap\SwapService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Fluxo de trocas entre funcionárias (PRD F5): ver com quem se pode trocar
 * antes de pedir, pedir, e a colega/admin decidirem. Toda a validação de
 * regras (quem é candidata, se a troca ainda é válida) vem do solver
 * (ADR-0002) — este controller só orquestra.
 */
class SwapController extends Controller
{
    public function create(Request $request, ShiftAssignment $assignment, SolverClient $solver): Response
    {
        $employee = $this->employeeOrAbort($request);

        abort_unless($assignment->employee_id === $employee->id, 403, 'Este turno não é teu.');

        $assignment->loadMissing(['schedule', 'shiftType']);
        $schedule = $assignment->schedule;
        $date = $assignment->date->toDateString();

        abort_unless($schedule->isPublished(), 403, 'Só é possível trocar turnos de uma escala publicada.');
        abort_if($date < Carbon::today()->toDateString(), 403, 'Não é possível trocar um turno já passado.');

        $result = $solver->swapCandidates($schedule, $employee, $date);
        $candidates = $this->candidatesWithNames($result);

        return Inertia::render('swaps/create', [
            'assignment' => [
                'id' => $assignment->id,
                'date' => $date,
                'shift_code' => $assignment->shiftType?->code,
            ],
            'candidates' => $candidates,
        ]);
    }

    public function store(StoreSwapRequest $request, SolverClient $solver, SwapService $swaps): RedirectResponse
    {
        $employee = $this->employeeOrAbort($request);

        $requesterAssignment = ShiftAssignment::query()
            ->with(['schedule'])
            ->findOrFail($request->validated('requester_assignment_id'));

        abort_unless($requesterAssignment->employee_id === $employee->id, 403, 'Este turno não é teu.');

        $schedule = $requesterAssignment->schedule;
        $date = $requesterAssignment->date->toDateString();

        abort_unless($schedule->isPublished(), 403, 'Só é possível trocar turnos de uma escala publicada.');
        abort_if($date < Carbon::today()->toDateString(), 403, 'Não é possível trocar um turno já passado.');

        $targetEmployee = Employee::query()->findOrFail($request->validated('target_employee_id'));
        abort_if($targetEmployee->id === $employee->id, 422, 'Não podes trocar contigo própria.');

        $targetAssignment = ShiftAssignment::query()
            ->where('schedule_id', $schedule->id)
            ->where('employee_id', $targetEmployee->id)
            ->whereDate('date', $date)
            ->first();

        abort_if($targetAssignment === null, 422, 'Essa colega não tem turno nesse dia.');

        // Revalidamos com o mesmo /swap-candidates que alimentou o ecrã "com
        // quem posso trocar" (create()) — garante que a colega escolhida
        // continua a ser uma candidata válida no momento do pedido, sem
        // reimplementar a lógica de regras aqui (ADR-0002).
        $result = $solver->swapCandidates($schedule, $employee, $date);
        $stillCandidate = collect($result['candidates'] ?? [])->contains(
            fn (array $candidate) => (int) $candidate['employee_id'] === $targetEmployee->id
        );

        abort_unless($stillCandidate, 422, 'Essa colega já não está disponível para esta troca.');

        $swapRequest = SwapRequest::create([
            'schedule_id' => $schedule->id,
            'requester_employee_id' => $employee->id,
            'target_employee_id' => $targetEmployee->id,
            'requester_assignment_id' => $requesterAssignment->id,
            'target_assignment_id' => $targetAssignment->id,
            'status' => SwapStatus::Pending,
            'validation' => $result,
            'admin_approval_required' => $employee->organization->swapRequiresAdminApproval(),
        ]);

        $swaps->notifyRequested($swapRequest);

        return redirect()->route('swaps.index')->with('success', 'Pedido de troca enviado.');
    }

    public function index(Request $request): Response
    {
        // Nullable de propósito (ex.: admins sem perfil de funcionária associado
        // podem ter este item na navegação) — mostra listas vazias em vez de 403.
        $employee = $request->user()->employee;

        $withRelations = ['requester', 'target', 'requesterAssignment.shiftType', 'targetAssignment.shiftType'];

        $sent = $employee === null ? collect() : SwapRequest::query()
            ->where('requester_employee_id', $employee->id)
            ->with($withRelations)
            ->latest()
            ->get();

        $received = $employee === null ? collect() : SwapRequest::query()
            ->where('target_employee_id', $employee->id)
            ->with($withRelations)
            ->latest()
            ->get();

        return Inertia::render('swaps/index', [
            'sent' => $sent->map(fn (SwapRequest $swapRequest) => $this->present($swapRequest))->values(),
            'received' => $received->map(fn (SwapRequest $swapRequest) => $this->present($swapRequest))->values(),
        ]);
    }

    public function accept(Request $request, SwapRequest $swapRequest, SwapService $swaps): RedirectResponse
    {
        $employee = $this->employeeOrAbort($request);

        abort_unless($swapRequest->target_employee_id === $employee->id, 403);
        abort_unless($swapRequest->status === SwapStatus::Pending, 400, 'Este pedido já foi decidido.');

        $result = $swaps->revalidate($swapRequest);

        if (! ($result['valid'] ?? false)) {
            $swapRequest->forceFill(['status' => SwapStatus::Rejected, 'validation' => $result, 'decided_at' => now()])->save();

            $reason = $this->explainViolations($result);
            $swaps->notifyRejected($swapRequest, $reason);

            return redirect()->route('swaps.index')->with('error', "A troca já não é válida: {$reason}");
        }

        if ($swapRequest->admin_approval_required) {
            $swapRequest->forceFill(['status' => SwapStatus::Accepted, 'validation' => $result, 'accepted_at' => now()])->save();

            $swaps->notifyAwaitingApproval($swapRequest);

            return redirect()->route('swaps.index')->with('success', 'Troca aceite — aguarda aprovação do admin.');
        }

        $swapRequest->forceFill(['validation' => $result])->save();
        $swaps->apply($swapRequest);

        return redirect()->route('swaps.index')->with('success', 'Troca aceite e aplicada à escala.');
    }

    public function decline(Request $request, SwapRequest $swapRequest, SwapService $swaps): RedirectResponse
    {
        $employee = $this->employeeOrAbort($request);

        abort_unless($swapRequest->target_employee_id === $employee->id, 403);
        abort_unless($swapRequest->status === SwapStatus::Pending, 400, 'Este pedido já foi decidido.');

        $swapRequest->forceFill(['status' => SwapStatus::Declined, 'decided_at' => now()])->save();

        $swaps->notifyDeclined($swapRequest);

        return redirect()->route('swaps.index')->with('success', 'Pedido de troca recusado.');
    }

    public function cancel(Request $request, SwapRequest $swapRequest, SwapService $swaps): RedirectResponse
    {
        $employee = $this->employeeOrAbort($request);

        abort_unless($swapRequest->requester_employee_id === $employee->id, 403);
        abort_unless($swapRequest->status === SwapStatus::Pending, 400, 'Este pedido já foi decidido.');

        $swapRequest->forceFill(['status' => SwapStatus::Cancelled, 'decided_at' => now()])->save();

        $swaps->notifyCancelled($swapRequest);

        return redirect()->route('swaps.index')->with('success', 'Pedido de troca cancelado.');
    }

    private function employeeOrAbort(Request $request): Employee
    {
        $employee = $request->user()->employee;

        abort_if($employee === null, 403, 'Sem perfil de funcionária associado.');

        return $employee;
    }

    /**
     * @return array<int, array{employee_id:int, name:string, shift:?string}>
     */
    private function candidatesWithNames(array $result): array
    {
        $candidates = collect($result['candidates'] ?? []);
        $employees = Employee::query()->whereIn('id', $candidates->pluck('employee_id'))->get()->keyBy('id');

        return $candidates
            ->filter(fn (array $candidate) => $employees->has($candidate['employee_id']))
            ->map(fn (array $candidate) => [
                'employee_id' => $candidate['employee_id'],
                'name' => $employees[$candidate['employee_id']]->name,
                'shift' => $candidate['shift'] ?? null,
            ])
            ->values()
            ->all();
    }

    private function present(SwapRequest $swapRequest): array
    {
        return [
            'id' => $swapRequest->id,
            'status' => $swapRequest->status->value,
            'requester' => ['id' => $swapRequest->requester->id, 'name' => $swapRequest->requester->name],
            'target' => ['id' => $swapRequest->target->id, 'name' => $swapRequest->target->name],
            'date' => $swapRequest->requesterAssignment->date->toDateString(),
            'requester_shift' => $swapRequest->requesterAssignment->shiftType?->code,
            'target_shift' => $swapRequest->targetAssignment->shiftType?->code,
            'admin_approval_required' => $swapRequest->admin_approval_required,
            'created_at' => $swapRequest->created_at?->toIso8601String(),
            'decided_at' => $swapRequest->decided_at?->toIso8601String(),
            'applied_at' => $swapRequest->applied_at?->toIso8601String(),
        ];
    }

    private function explainViolations(array $result): string
    {
        $violations = collect($result['violations'] ?? []);

        if ($violations->isEmpty()) {
            return 'o estado da escala mudou entretanto.';
        }

        return $violations->map(fn (array $violation) => $violation['message'] ?? $violation['rule'] ?? '')->filter()->implode('; ');
    }
}
