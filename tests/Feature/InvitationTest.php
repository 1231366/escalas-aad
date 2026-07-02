<?php

use App\Enums\Regime;
use App\Models\Employee;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\InvitationAccepted;
use App\Notifications\InvitationInvite;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();

    $this->org = Organization::factory()->create();
    $this->admin = User::factory()->admin()->inOrganization($this->org)->create();
});

test('admin can create an invitation with profile presets', function () {
    $this->actingAs($this->admin)
        ->post('/admin/convites', [
            'name' => 'Sofia',
            'email' => 'sofia@example.com',
            'role' => 'EMPLOYEE',
            'regime' => 'NOITE',
            'contract' => 'H37_30',
            'fixa_noite' => true,
        ])
        ->assertRedirect();

    $invitation = Invitation::withoutGlobalScopes()->where('email', 'sofia@example.com')->first();

    expect($invitation)->not->toBeNull()
        ->and($invitation->regime)->toBe(Regime::Noite)
        ->and($invitation->fixa_noite)->toBeTrue()
        ->and($invitation->isPending())->toBeTrue();

    Notification::assertSentOnDemand(InvitationInvite::class);
});

test('employee cannot create invitations', function () {
    $employee = User::factory()->inOrganization($this->org)->create();

    $this->actingAs($employee)
        ->post('/admin/convites', ['name' => 'X', 'email' => 'x@x.pt'])
        ->assertForbidden();
});

test('duplicate pending invitation for same email is rejected', function () {
    Invitation::factory()->for($this->org)->create(['email' => 'dup@example.com']);

    $this->actingAs($this->admin)
        ->post('/admin/convites', [
            'name' => 'Dup',
            'email' => 'dup@example.com',
            'role' => 'EMPLOYEE',
            'regime' => 'DIA',
            'contract' => 'H40',
        ])
        ->assertSessionHasErrors('email');
});

test('accepting an invitation creates user and employee with presets and logs in', function () {
    $invitation = Invitation::factory()->for($this->org)->create([
        'email' => 'nova@example.com',
        'name' => 'Nova',
        'regime' => Regime::Noite,
        'fixa_noite' => true,
        'created_by' => $this->admin->id,
    ]);

    $this->get("/convite/{$invitation->token}")->assertOk();

    $this->post("/convite/{$invitation->token}", [
        'name' => 'Nova Funcionária',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertRedirect(route('dashboard'));

    $user = User::where('email', 'nova@example.com')->first();
    $employee = Employee::withoutGlobalScopes()->where('user_id', $user->id)->first();

    expect($user->organization_id)->toBe($this->org->id)
        ->and($user->isAdmin())->toBeFalse()
        ->and($user->calendar_token)->not->toBeNull()
        ->and($employee->regime)->toBe(Regime::Noite)
        ->and($employee->fixa_noite)->toBeTrue()
        ->and($invitation->fresh()->status())->toBe('accepted');

    $this->assertAuthenticatedAs($user);

    Notification::assertSentTo($this->admin, InvitationAccepted::class);
});

test('expired and revoked invitations cannot be accepted', function () {
    $expired = Invitation::factory()->for($this->org)->expired()->create(['created_by' => $this->admin->id]);
    $revoked = Invitation::factory()->for($this->org)->revoked()->create(['created_by' => $this->admin->id]);

    $payload = ['name' => 'X', 'password' => 'password123', 'password_confirmation' => 'password123'];

    $this->post("/convite/{$expired->token}", $payload)->assertGone();
    $this->post("/convite/{$revoked->token}", $payload)->assertGone();
});

test('accepted invitation cannot be reused', function () {
    $invitation = Invitation::factory()->for($this->org)->create([
        'accepted_at' => now(),
        'created_by' => $this->admin->id,
    ]);

    $this->post("/convite/{$invitation->token}", [
        'name' => 'X',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertGone();
});

test('admin can revoke a pending invitation', function () {
    $invitation = Invitation::factory()->for($this->org)->create(['created_by' => $this->admin->id]);

    $this->actingAs($this->admin)
        ->post("/admin/convites/{$invitation->id}/revogar")
        ->assertRedirect();

    expect($invitation->fresh()->status())->toBe('revoked');
});

test('resend regenerates token and extends expiry', function () {
    $invitation = Invitation::factory()->for($this->org)->expired()->create(['created_by' => $this->admin->id]);
    $oldToken = $invitation->token;

    $this->actingAs($this->admin)
        ->post("/admin/convites/{$invitation->id}/reenviar")
        ->assertRedirect();

    $fresh = $invitation->fresh();

    expect($fresh->token)->not->toBe($oldToken)
        ->and($fresh->isPending())->toBeTrue();

    Notification::assertSentOnDemand(InvitationInvite::class);
});
