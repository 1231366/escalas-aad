<?php

use App\Enums\AssignmentOrigin;
use App\Enums\ScheduleStatus;
use App\Models\Absence;
use App\Models\CoverageRule;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\Schedule;
use App\Models\ShiftAssignment;
use App\Models\ShiftType;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->admin = User::factory()->admin()->inOrganization($this->org)->create();

    $this->morning = ShiftType::factory()->for($this->org)->create(['code' => 'M']);
    $this->night = ShiftType::factory()->tarde()->for($this->org)->create(['code' => 'N']);

    CoverageRule::create([
        'organization_id' => $this->org->id, 'shift_type_id' => $this->morning->id, 'weekday' => 0, 'required' => 4,
    ]);
    CoverageRule::create([
        'organization_id' => $this->org->id, 'shift_type_id' => $this->night->id, 'weekday' => 0, 'required' => 2,
    ]);
});

test('registering an absence with an existing published schedule reports the coverage gap it creates', function () {
    $schedule = Schedule::factory()->for($this->org)->create([
        'status' => ScheduleStatus::Published,
        'period_start' => '2026-09-01', // terça-feira
        'period_end' => '2026-09-30',
    ]);

    $employee = Employee::factory()->for($this->org)->create();

    // única pessoa de manhã (segunda-feira) nesse dia: sem ela a cobertura cai a 0/4
    ShiftAssignment::factory()->create([
        'schedule_id' => $schedule->id, 'employee_id' => $employee->id,
        'date' => '2026-09-07', 'shift_type_id' => $this->morning->id, 'origin' => AssignmentOrigin::Generated,
    ]);

    $this->actingAs($this->admin)->post('/admin/ausencias', [
        'employee_id' => $employee->id,
        'start_date' => '2026-09-07',
        'end_date' => '2026-09-07',
        'type' => 'SICK',
    ])->assertRedirect();

    $absence = Absence::first();

    expect($absence->schedule_id)->toBe($schedule->id)
        ->and($absence->coverage_gaps)->toHaveCount(1)
        ->and($absence->coverage_gaps[0]['shift_code'])->toBe('M')
        ->and($absence->coverage_gaps[0]['after'])->toBe(0)
        ->and($absence->coverage_gaps[0]['required'])->toBe(4);
});

test('registering an absence without a published schedule covering the period reports no gaps', function () {
    $employee = Employee::factory()->for($this->org)->create();

    $this->actingAs($this->admin)->post('/admin/ausencias', [
        'employee_id' => $employee->id,
        'start_date' => '2026-09-07',
        'end_date' => '2026-09-07',
        'type' => 'OTHER',
    ])->assertRedirect();

    $absence = Absence::first();

    expect($absence->schedule_id)->toBeNull()
        ->and($absence->coverage_gaps)->toBe([]);
});

test('reoptimization only replaces assignments on or after the cutoff and preserves the past', function () {
    $schedule = Schedule::factory()->for($this->org)->create([
        'status' => ScheduleStatus::Published,
        'period_start' => '2026-09-01',
        'period_end' => '2026-09-10',
    ]);

    $employee = Employee::factory()->for($this->org)->create();

    $past = ShiftAssignment::factory()->create([
        'schedule_id' => $schedule->id, 'employee_id' => $employee->id,
        'date' => '2026-09-02', 'shift_type_id' => $this->morning->id, 'origin' => AssignmentOrigin::Generated,
    ]);

    $futureBeforeCutoff = ShiftAssignment::factory()->create([
        'schedule_id' => $schedule->id, 'employee_id' => $employee->id,
        'date' => now()->toDateString(), 'shift_type_id' => $this->morning->id, 'origin' => AssignmentOrigin::Generated,
    ]);

    $this->travelTo('2026-09-03');

    $absence = Absence::create([
        'organization_id' => $this->org->id,
        'employee_id' => $employee->id,
        'start_date' => '2026-09-05',
        'end_date' => '2026-09-06',
        'type' => 'SICK',
        'schedule_id' => $schedule->id,
    ]);

    $cutoff = $absence->reoptimizationCutoff($schedule);
    expect($cutoff->toDateString())->toBe('2026-09-05');

    Http::fake(['*/generate' => Http::response([
        'status' => 'FEASIBLE',
        'assignments' => [
            ['employee_id' => $employee->id, 'date' => '2026-09-05', 'shift' => null],
            ['employee_id' => $employee->id, 'date' => '2026-09-06', 'shift' => null],
            ['employee_id' => $employee->id, 'date' => '2026-09-07', 'shift' => 'N'],
        ],
    ], 200)]);

    $this->actingAs($this->admin)->post("/admin/ausencias/{$absence->id}/reotimizar")->assertRedirect();

    Http::assertSent(function ($request) {
        return $request->url() === config('services.solver.url').'/generate'
            && $request['period_start'] === '2026-09-05'
            && $request['period_end'] === '2026-09-10';
    });

    expect($past->fresh()->shift_type_id)->toBe($this->morning->id);

    $newAssignment = ShiftAssignment::where('schedule_id', $schedule->id)->where('date', '2026-09-07')->first();
    expect($newAssignment->shiftType->code)->toBe('N')
        ->and($newAssignment->origin)->toBe(AssignmentOrigin::Generated);

    expect($absence->fresh()->reoptimization_status)->toBe('FEASIBLE')
        ->and($absence->fresh()->reoptimized_at)->not->toBeNull();
});

test('an infeasible reoptimization leaves assignments untouched and records the conflicts', function () {
    $schedule = Schedule::factory()->for($this->org)->create([
        'status' => ScheduleStatus::Published,
        'period_start' => '2026-09-01',
        'period_end' => '2026-09-10',
    ]);
    $employee = Employee::factory()->for($this->org)->create();

    $existing = ShiftAssignment::factory()->create([
        'schedule_id' => $schedule->id, 'employee_id' => $employee->id,
        'date' => '2026-09-07', 'shift_type_id' => $this->morning->id, 'origin' => AssignmentOrigin::Generated,
    ]);

    $absence = Absence::create([
        'organization_id' => $this->org->id, 'employee_id' => $employee->id,
        'start_date' => '2026-09-06', 'end_date' => '2026-09-06', 'type' => 'SICK', 'schedule_id' => $schedule->id,
    ]);

    Http::fake(['*/generate' => Http::response([
        'status' => 'INFEASIBLE',
        'conflicts' => [['rule' => 'H1', 'message' => 'Faltam turnos', 'date' => '2026-09-07', 'employee_id' => null]],
    ], 200)]);

    $this->actingAs($this->admin)->post("/admin/ausencias/{$absence->id}/reotimizar")->assertRedirect();

    expect($existing->fresh()->shift_type_id)->toBe($this->morning->id)
        ->and($absence->fresh()->reoptimization_status)->toBe('INFEASIBLE')
        ->and($absence->fresh()->reoptimization_conflicts)->toHaveCount(1);
});

test('admin can delete an absence', function () {
    $employee = Employee::factory()->for($this->org)->create();
    $absence = Absence::create([
        'organization_id' => $this->org->id, 'employee_id' => $employee->id,
        'start_date' => '2026-09-01', 'end_date' => '2026-09-01', 'type' => 'OTHER',
    ]);

    $this->actingAs($this->admin)->delete("/admin/ausencias/{$absence->id}")->assertRedirect();

    expect(Absence::find($absence->id))->toBeNull();
});

test('non-admin cannot manage absences', function () {
    $employee = User::factory()->inOrganization($this->org)->create();

    $this->actingAs($employee)->get('/admin/ausencias')->assertForbidden();
    $this->actingAs($employee)->post('/admin/ausencias', [])->assertForbidden();
});

test('absences are tenant scoped', function () {
    $otherOrg = Organization::factory()->create();
    $otherEmployee = Employee::factory()->for($otherOrg)->create();

    Absence::create([
        'organization_id' => $otherOrg->id, 'employee_id' => $otherEmployee->id,
        'start_date' => '2026-09-01', 'end_date' => '2026-09-01', 'type' => 'OTHER',
    ]);

    $this->actingAs($this->admin)->get('/admin/ausencias')->assertInertia(fn ($page) => $page->has('absences', 0));
});
