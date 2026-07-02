<?php

use App\Models\CoverageRule;
use App\Models\Organization;
use App\Models\RuleConfig;
use App\Models\ShiftType;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->admin = User::factory()->admin()->inOrganization($this->org)->create();

    $this->shiftM = ShiftType::factory()->for($this->org)->create();
    $this->shiftT = ShiftType::factory()->tarde()->for($this->org)->create();
    $this->shiftN = ShiftType::factory()->noite()->for($this->org)->create();

    foreach ([$this->shiftM->id => 4, $this->shiftT->id => 3, $this->shiftN->id => 2] as $shiftTypeId => $required) {
        foreach (range(0, 6) as $weekday) {
            CoverageRule::create([
                'organization_id' => $this->org->id,
                'shift_type_id' => $shiftTypeId,
                'weekday' => $weekday,
                'required' => $required,
            ]);
        }
    }
});

test('admin can view the rules page with shift types, coverage and parameters', function () {
    $response = $this->actingAs($this->admin)->get('/admin/regras');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('admin/rules/index')
        ->has('shift_types', 3)
        ->has('coverage', 21)
        ->where('rule_configs.hour_bank_weekly_tolerance', 4)
        ->where('rule_configs.max_consecutive_work_days', 6)
        ->where('rule_configs.ff_window_weeks', 7)
        ->where('rule_configs.ff_monthly', true)
    );
});

test('employee cannot view the rules page', function () {
    $employee = User::factory()->inOrganization($this->org)->create();

    $this->actingAs($employee)->get('/admin/regras')->assertForbidden();
});

test('admin can update coverage and it persists', function () {
    $this->actingAs($this->admin)->put('/admin/regras/cobertura', [
        'coverage' => [
            ['shift_type_id' => $this->shiftM->id, 'weekday' => 0, 'required' => 5],
            ['shift_type_id' => $this->shiftT->id, 'weekday' => 0, 'required' => 2],
        ],
    ])->assertRedirect();

    expect(CoverageRule::where(['shift_type_id' => $this->shiftM->id, 'weekday' => 0])->first()->required)->toBe(5)
        ->and(CoverageRule::where(['shift_type_id' => $this->shiftT->id, 'weekday' => 0])->first()->required)->toBe(2);
});

test('coverage update rejects negative required and invalid weekday', function () {
    $this->actingAs($this->admin)->put('/admin/regras/cobertura', [
        'coverage' => [
            ['shift_type_id' => $this->shiftM->id, 'weekday' => 0, 'required' => -1],
            ['shift_type_id' => $this->shiftT->id, 'weekday' => 7, 'required' => 3],
        ],
    ])->assertSessionHasErrors([
        'coverage.0.required',
        'coverage.1.weekday',
    ]);
});

test('admin can update rule parameters and they persist in RuleConfig', function () {
    $this->actingAs($this->admin)->put('/admin/regras/parametros', [
        'hour_bank_weekly_tolerance' => 6.5,
        'max_consecutive_work_days' => 5,
        'ff_window_weeks' => 4,
        'ff_monthly' => false,
    ])->assertRedirect();

    $this->actingAs($this->admin);

    expect(RuleConfig::get('hour_bank_weekly_tolerance'))->toEqual(6.5)
        ->and(RuleConfig::get('max_consecutive_work_days'))->toEqual(5)
        ->and(RuleConfig::get('ff_window_weeks'))->toEqual(4)
        ->and(RuleConfig::get('ff_monthly'))->toBeFalse();
});

test('rule parameters reject out-of-range values', function () {
    $this->actingAs($this->admin)->put('/admin/regras/parametros', [
        'hour_bank_weekly_tolerance' => 20,
        'max_consecutive_work_days' => 7,
        'ff_window_weeks' => 13,
        'ff_monthly' => true,
    ])->assertSessionHasErrors(['hour_bank_weekly_tolerance', 'max_consecutive_work_days', 'ff_window_weeks']);
});

test('admin can update a shift type schedule and color', function () {
    $this->actingAs($this->admin)->put("/admin/regras/turnos/{$this->shiftM->id}", [
        'starts_at' => '07:00',
        'ends_at' => '15:00',
        'color' => '#ff0000',
    ])->assertRedirect();

    $this->shiftM->refresh();

    expect($this->shiftM->starts_at)->toContain('07:00')
        ->and($this->shiftM->ends_at)->toContain('15:00')
        ->and($this->shiftM->color)->toBe('#ff0000')
        ->and($this->shiftM->code)->toBe('M');
});

test('shift type update rejects invalid time and color format', function () {
    $this->actingAs($this->admin)->put("/admin/regras/turnos/{$this->shiftM->id}", [
        'starts_at' => '25:99',
        'ends_at' => '15:00',
        'color' => 'not-a-color',
    ])->assertSessionHasErrors(['starts_at', 'color']);
});

test('rule settings are tenant-scoped', function () {
    $orgB = Organization::factory()->create();
    $adminB = User::factory()->admin()->inOrganization($orgB)->create();

    $shiftB = ShiftType::factory()->for($orgB)->create();
    CoverageRule::create([
        'organization_id' => $orgB->id,
        'shift_type_id' => $shiftB->id,
        'weekday' => 0,
        'required' => 9,
    ]);
    RuleConfig::create([
        'organization_id' => $orgB->id,
        'key' => 'max_consecutive_work_days',
        'value' => 3,
    ]);

    $responseA = $this->actingAs($this->admin)->get('/admin/regras');
    $responseA->assertInertia(fn (Assert $page) => $page
        ->has('shift_types', 3)
        ->where('rule_configs.max_consecutive_work_days', 6)
    );

    $responseB = $this->actingAs($adminB)->get('/admin/regras');
    $responseB->assertInertia(fn (Assert $page) => $page
        ->has('shift_types', 1)
        ->where('rule_configs.max_consecutive_work_days', 3)
    );

    // org B não consegue editar um turno da org A (fora do global scope -> 404)
    $this->actingAs($adminB)->put("/admin/regras/turnos/{$this->shiftM->id}", [
        'starts_at' => '09:00',
        'ends_at' => '17:00',
        'color' => '#000000',
    ])->assertNotFound();
});
