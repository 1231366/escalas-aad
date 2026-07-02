<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Employee;
use App\Models\Invitation;
use App\Models\User;
use App\Notifications\InvitationAccepted;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Fluxo público de aceitação de convite (PRD F2).
 * O token é o único segredo; a página é acessível sem login.
 */
class InvitationAcceptanceController extends Controller
{
    public function show(string $token): Response
    {
        $invitation = Invitation::withoutGlobalScopes()
            ->where('token', $token)
            ->with('organization')
            ->firstOrFail();

        return Inertia::render('invitations/accept', [
            'invitation' => [
                'token' => $invitation->token,
                'name' => $invitation->name,
                'email' => $invitation->email,
                'organization' => $invitation->organization->name,
                'regime_label' => $invitation->regime->label(),
                'contract_label' => $invitation->contract->label(),
                'fixa_noite' => $invitation->fixa_noite,
                'status' => $invitation->status(),
            ],
        ]);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $invitation = Invitation::withoutGlobalScopes()
            ->where('token', $token)
            ->with('organization')
            ->firstOrFail();

        abort_unless($invitation->isPending(), 410, 'Este convite já não é válido.');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = DB::transaction(function () use ($invitation, $validated) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $invitation->email,
                'password' => $validated['password'],
                'role' => $invitation->role,
                'organization_id' => $invitation->organization_id,
                'email_verified_at' => now(), // o convite chegou por email: está verificado
            ]);
            $user->regenerateCalendarToken();

            if ($invitation->role === Role::Employee) {
                Employee::create([
                    'organization_id' => $invitation->organization_id,
                    'user_id' => $user->id,
                    'name' => $validated['name'],
                    'regime' => $invitation->regime,
                    'contract' => $invitation->contract,
                    'fixa_noite' => $invitation->fixa_noite,
                    'active' => true,
                ]);
            }

            $invitation->update(['accepted_at' => now()]);

            return $user;
        });

        $invitation->creator->notify(new InvitationAccepted($invitation, $user));

        Auth::login($user);

        return redirect()->route('dashboard')
            ->with('success', 'Bem-vinda! A tua conta está criada.');
    }
}
