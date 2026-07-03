<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SwapStatus;
use App\Http\Controllers\Controller;
use App\Models\SwapRequest;
use App\Services\Swap\SwapService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Supervisão de trocas pelo admin (PRD F5): o admin não é parte da troca,
 * só aprova quando a organização o exige (Organization::swapRequiresAdminApproval()).
 */
class SwapController extends Controller
{
    public function index(): Response
    {
        $withRelations = ['requester', 'target', 'requesterAssignment.shiftType', 'targetAssignment.shiftType'];

        $swaps = SwapRequest::query()
            ->with($withRelations)
            ->latest()
            ->get()
            ->map(fn (SwapRequest $swapRequest) => [
                'id' => $swapRequest->id,
                'status' => $swapRequest->status->value,
                'requester' => ['id' => $swapRequest->requester->id, 'name' => $swapRequest->requester->name],
                'target' => ['id' => $swapRequest->target->id, 'name' => $swapRequest->target->name],
                'date' => $swapRequest->requesterAssignment->date->toDateString(),
                'requester_shift' => $swapRequest->requesterAssignment->shiftType?->code,
                'target_shift' => $swapRequest->targetAssignment->shiftType?->code,
                'admin_approval_required' => $swapRequest->admin_approval_required,
                'created_at' => $swapRequest->created_at?->toIso8601String(),
                'accepted_at' => $swapRequest->accepted_at?->toIso8601String(),
                'decided_at' => $swapRequest->decided_at?->toIso8601String(),
                'applied_at' => $swapRequest->applied_at?->toIso8601String(),
            ])
            ->values();

        return Inertia::render('admin/swaps/index', [
            'swaps' => $swaps,
        ]);
    }

    public function approve(SwapRequest $swapRequest, SwapService $swaps): RedirectResponse
    {
        abort_unless($swapRequest->status === SwapStatus::Accepted, 400, 'Só é possível aprovar pedidos aceites pela colega.');

        $result = $swaps->revalidate($swapRequest);

        if (! ($result['valid'] ?? false)) {
            $swapRequest->forceFill(['status' => SwapStatus::Rejected, 'validation' => $result, 'decided_at' => now()])->save();

            $reason = collect($result['violations'] ?? [])
                ->map(fn (array $violation) => $violation['message'] ?? $violation['rule'] ?? '')
                ->filter()
                ->implode('; ') ?: 'o estado da escala mudou entretanto.';

            $swaps->notifyRejected($swapRequest, $reason);

            return back()->with('error', "A troca já não é válida: {$reason}");
        }

        $swapRequest->forceFill(['validation' => $result])->save();
        $swaps->apply($swapRequest);

        return back()->with('success', 'Troca aprovada e aplicada à escala.');
    }

    public function reject(SwapRequest $swapRequest, SwapService $swaps): RedirectResponse
    {
        abort_unless($swapRequest->status === SwapStatus::Accepted, 400, 'Só é possível rejeitar pedidos aceites pela colega.');

        $swapRequest->forceFill(['status' => SwapStatus::Rejected, 'decided_at' => now()])->save();

        $swaps->notifyRejected($swapRequest, 'rejeitada pelo admin.');

        return back()->with('success', 'Troca rejeitada.');
    }
}
