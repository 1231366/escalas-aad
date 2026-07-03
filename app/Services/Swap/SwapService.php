<?php

namespace App\Services\Swap;

use App\Enums\AssignmentOrigin;
use App\Enums\Role;
use App\Enums\SwapStatus;
use App\Models\AuditLog;
use App\Models\ShiftAssignment;
use App\Models\SwapRequest;
use App\Models\User;
use App\Notifications\SwapApplied;
use App\Notifications\SwapAwaitingApproval;
use App\Notifications\SwapCancelled;
use App\Notifications\SwapDeclined;
use App\Notifications\SwapRejected;
use App\Notifications\SwapRequested;
use App\Services\Solver\SolverClient;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Orquestra o ciclo de vida de um pedido de troca (PRD F5): revalidação pelo
 * solver antes de aplicar, aplicação transacional à escala e as notificações
 * de cada transição. Os controllers (SwapController, Admin\SwapController)
 * ficam finos — só tratam de autorização e HTTP.
 */
class SwapService
{
    public function __construct(private SolverClient $solver) {}

    /**
     * Escala hipotética completa com a troca já aplicada, no formato que o
     * solver espera — para revalidar antes de aceitar/aprovar (o estado pode
     * ter mudado desde o pedido).
     *
     * @return list<array{employee_id:int,date:string,shift:?string}>
     */
    public function hypotheticalAssignments(SwapRequest $swapRequest): array
    {
        $schedule = $swapRequest->schedule;
        $schedule->loadMissing(['assignments.shiftType']);

        $requesterAssignment = $schedule->assignments->firstWhere('id', $swapRequest->requester_assignment_id);
        $targetAssignment = $schedule->assignments->firstWhere('id', $swapRequest->target_assignment_id);

        return $schedule->assignments
            ->map(function (ShiftAssignment $assignment) use ($requesterAssignment, $targetAssignment) {
                $code = match ($assignment->id) {
                    $requesterAssignment->id => $targetAssignment->shiftType?->code,
                    $targetAssignment->id => $requesterAssignment->shiftType?->code,
                    default => $assignment->shiftType?->code,
                };

                return [
                    'employee_id' => $assignment->employee_id,
                    'date' => $assignment->date->toDateString(),
                    'shift' => $code,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Revalida a troca contra o estado atual completo da escala via /validate
     * (ADR-0002 — o solver é a única fonte da lógica de regras).
     */
    public function revalidate(SwapRequest $swapRequest): array
    {
        return $this->solver->validate($swapRequest->schedule, $this->hypotheticalAssignments($swapRequest));
    }

    /**
     * Troca os shift_type_id dos dois assignments, marca origin=SWAP,
     * fecha o pedido como APPLIED e notifica ambas as funcionárias + admins.
     */
    public function apply(SwapRequest $swapRequest): void
    {
        DB::transaction(function () use ($swapRequest) {
            $requesterAssignment = ShiftAssignment::query()
                ->whereKey($swapRequest->requester_assignment_id)
                ->lockForUpdate()
                ->firstOrFail();

            $targetAssignment = ShiftAssignment::query()
                ->whereKey($swapRequest->target_assignment_id)
                ->lockForUpdate()
                ->firstOrFail();

            $requesterShiftTypeId = $requesterAssignment->shift_type_id;
            $targetShiftTypeId = $targetAssignment->shift_type_id;

            $requesterAssignment->forceFill(['shift_type_id' => $targetShiftTypeId, 'origin' => AssignmentOrigin::Swap])->save();
            $targetAssignment->forceFill(['shift_type_id' => $requesterShiftTypeId, 'origin' => AssignmentOrigin::Swap])->save();

            $swapRequest->forceFill(['status' => SwapStatus::Applied, 'applied_at' => now()])->save();

            AuditLog::record('swap.applied', $swapRequest, [
                'requester_employee_id' => $swapRequest->requester_employee_id,
                'target_employee_id' => $swapRequest->target_employee_id,
                'date' => $requesterAssignment->date->toDateString(),
            ]);
        });

        $swapRequest->refresh();

        Notification::send($this->employeesAndAdmins($swapRequest), new SwapApplied($swapRequest));
    }

    /**
     * @return EloquentCollection<int, User>
     */
    public function admins(int $organizationId): EloquentCollection
    {
        return User::query()
            ->where('organization_id', $organizationId)
            ->where('role', Role::Admin)
            ->get();
    }

    public function notifyRequested(SwapRequest $swapRequest): void
    {
        $swapRequest->loadMissing('target.user');

        $recipients = collect([$swapRequest->target->user])
            ->filter()
            ->merge($this->admins($swapRequest->organization_id));

        Notification::send($recipients, new SwapRequested($swapRequest));
    }

    public function notifyAwaitingApproval(SwapRequest $swapRequest): void
    {
        Notification::send($this->admins($swapRequest->organization_id), new SwapAwaitingApproval($swapRequest));
    }

    public function notifyRejected(SwapRequest $swapRequest, string $reason): void
    {
        Notification::send($this->employees($swapRequest), new SwapRejected($swapRequest, $reason));
    }

    public function notifyDeclined(SwapRequest $swapRequest): void
    {
        $swapRequest->loadMissing('requester.user');

        $recipient = $swapRequest->requester->user;

        if ($recipient) {
            Notification::send($recipient, new SwapDeclined($swapRequest));
        }
    }

    public function notifyCancelled(SwapRequest $swapRequest): void
    {
        $swapRequest->loadMissing('target.user');

        $recipient = $swapRequest->target->user;

        if ($recipient) {
            Notification::send($recipient, new SwapCancelled($swapRequest));
        }
    }

    /**
     * @return Collection<int, User>
     */
    private function employees(SwapRequest $swapRequest): Collection
    {
        $swapRequest->loadMissing(['requester.user', 'target.user']);

        return collect([$swapRequest->requester->user, $swapRequest->target->user])
            ->filter()
            ->values();
    }

    /**
     * @return Collection<int, User>
     */
    private function employeesAndAdmins(SwapRequest $swapRequest): Collection
    {
        return $this->employees($swapRequest)->merge($this->admins($swapRequest->organization_id));
    }
}
