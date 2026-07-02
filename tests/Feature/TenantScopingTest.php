<?php

use App\Enums\Regime;
use App\Models\Employee;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\RuleConfig;
use App\Models\User;

test('queries are scoped to the authenticated user organization', function () {
    $orgA = Organization::factory()->create();
    $orgB = Organization::factory()->create();

    Employee::factory()->for($orgA)->count(3)->create();
    Employee::factory()->for($orgB)->count(2)->create();

    $adminA = User::factory()->admin()->inOrganization($orgA)->create();

    $this->actingAs($adminA);

    expect(Employee::count())->toBe(3)
        ->and(Employee::withoutGlobalScopes()->count())->toBe(5);
});

test('creating a model auto-fills the organization of the authenticated user', function () {
    $org = Organization::factory()->create();
    $admin = User::factory()->admin()->inOrganization($org)->create();

    $this->actingAs($admin);

    $employee = Employee::create([
        'name' => 'Nova AAD',
    ]);

    expect($employee->organization_id)->toBe($org->id);
});

test('unauthenticated context sees no tenant filter applied', function () {
    Employee::factory()->count(2)->create();

    expect(Employee::count())->toBe(2);
});

test('invitation status reflects lifecycle', function () {
    $pending = Invitation::factory()->create();
    $expired = Invitation::factory()->expired()->create();
    $revoked = Invitation::factory()->revoked()->create();
    $accepted = Invitation::factory()->create(['accepted_at' => now()]);

    expect($pending->status())->toBe('pending')
        ->and($pending->isPending())->toBeTrue()
        ->and($expired->status())->toBe('expired')
        ->and($revoked->status())->toBe('revoked')
        ->and($accepted->status())->toBe('accepted');
});

test('rule config get and set are tenant scoped', function () {
    $orgA = Organization::factory()->create();
    $orgB = Organization::factory()->create();
    $adminA = User::factory()->admin()->inOrganization($orgA)->create();
    $adminB = User::factory()->admin()->inOrganization($orgB)->create();

    $this->actingAs($adminA);
    RuleConfig::set('hour_bank_weekly_tolerance', 4.0);

    $this->actingAs($adminB);
    RuleConfig::set('hour_bank_weekly_tolerance', 8.0);

    // valores JSON perdem a distinção int/float no round-trip
    expect(RuleConfig::get('hour_bank_weekly_tolerance'))->toEqual(8);

    $this->actingAs($adminA);
    expect(RuleConfig::get('hour_bank_weekly_tolerance'))->toEqual(4);
});

test('regime determines night eligibility', function () {
    $employee = Employee::factory()->fixaNoite()->create();
    $dayOnly = Employee::factory()->regime(Regime::Dia)->create();

    expect($employee->elegivelNoite())->toBeTrue()
        ->and($employee->fixa_noite)->toBeTrue()
        ->and($dayOnly->elegivelNoite())->toBeFalse();
});
