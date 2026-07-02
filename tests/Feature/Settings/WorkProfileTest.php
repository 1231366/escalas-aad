<?php

use App\Enums\ContractType;
use App\Enums\Regime;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('work profile page shows employee data for an employee user', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->inOrganization($org)->create();
    $employee = Employee::factory()->for($org)->regime(Regime::Noite)->fixaNoite()->create([
        'user_id' => $user->id,
        'name' => 'Ana Silva',
        'contract' => ContractType::H40,
    ]);

    $response = $this->actingAs($user)->get('/settings/trabalho');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('settings/work-profile')
        ->where('employee.name', 'Ana Silva')
        ->where('employee.regime', 'NOITE')
        ->where('employee.regime_label', $employee->regime->label())
        ->where('employee.contract', 'H40')
        ->where('employee.weekly_hours', 40)
        ->where('employee.fixa_noite', true)
        ->where('employee.active', true)
    );
});

test('work profile page shows empty state for admin without employee profile', function () {
    $org = Organization::factory()->create();
    $admin = User::factory()->admin()->inOrganization($org)->create();

    $response = $this->actingAs($admin)->get('/settings/trabalho');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('settings/work-profile')
        ->where('employee', null)
    );
});

test('user can update notification preferences and wantsEmailFor reflects change', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->inOrganization($org)->create();

    expect($user->wantsEmailFor('schedule_published'))->toBeTrue();

    $response = $this->actingAs($user)->patch('/settings/notificacoes', [
        'email' => [
            'schedule_published' => false,
            'swap_request' => true,
        ],
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect('/settings/trabalho');

    $user->refresh();

    expect($user->wantsEmailFor('schedule_published'))->toBeFalse()
        ->and($user->wantsEmailFor('swap_request'))->toBeTrue()
        ->and($user->wantsEmailFor('invitation_accepted'))->toBeTrue();
});

test('guest is redirected to login when accessing work profile settings', function () {
    $this->get('/settings/trabalho')->assertRedirect(route('login'));
});

test('guest is redirected to login when updating notification preferences', function () {
    $this->patch('/settings/notificacoes', ['email' => ['schedule_published' => false]])
        ->assertRedirect(route('login'));
});
