<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInvitationRequest;
use App\Models\AuditLog;
use App\Models\Invitation;
use App\Notifications\InvitationInvite;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

class InvitationController extends Controller
{
    public function index(): Response
    {
        $invitations = Invitation::query()
            ->latest()
            ->get()
            ->map(fn (Invitation $invitation) => [
                'id' => $invitation->id,
                'name' => $invitation->name,
                'email' => $invitation->email,
                'role' => $invitation->role->value,
                'regime' => $invitation->regime->value,
                'regime_label' => $invitation->regime->label(),
                'contract' => $invitation->contract->value,
                'contract_label' => $invitation->contract->label(),
                'fixa_noite' => $invitation->fixa_noite,
                'status' => $invitation->status(),
                'expires_at' => $invitation->expires_at->toIso8601String(),
                'accept_url' => $invitation->isPending() ? $invitation->acceptUrl() : null,
                'whatsapp_url' => $invitation->isPending() ? $invitation->whatsappUrl() : null,
            ]);

        return Inertia::render('admin/invitations/index', [
            'invitations' => $invitations,
        ]);
    }

    public function store(StoreInvitationRequest $request): RedirectResponse
    {
        $invitation = Invitation::create([
            ...$request->validated(),
            'token' => Invitation::generateToken(),
            'expires_at' => now()->addDays(7),
            'created_by' => $request->user()->id,
        ]);

        Notification::route('mail', $invitation->email)
            ->notify(new InvitationInvite($invitation));

        AuditLog::record('invitation.created', $invitation, [
            'email' => $invitation->email,
            'regime' => $invitation->regime->value,
        ]);

        return back()->with('success', 'Convite criado. Partilha o link por WhatsApp ou email.');
    }

    public function resend(Invitation $invitation): RedirectResponse
    {
        abort_unless($invitation->status() !== 'accepted', 400);

        $invitation->update([
            'token' => Invitation::generateToken(),
            'expires_at' => now()->addDays(7),
            'revoked_at' => null,
        ]);

        Notification::route('mail', $invitation->email)
            ->notify(new InvitationInvite($invitation));

        AuditLog::record('invitation.resent', $invitation);

        return back()->with('success', 'Convite reenviado com novo link.');
    }

    public function revoke(Invitation $invitation): RedirectResponse
    {
        abort_unless($invitation->isPending(), 400);

        $invitation->update(['revoked_at' => now()]);

        AuditLog::record('invitation.revoked', $invitation);

        return back()->with('success', 'Convite revogado.');
    }
}
