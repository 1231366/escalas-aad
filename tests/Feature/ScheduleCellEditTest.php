<?php

use App\Enums\AssignmentOrigin;
use App\Enums\ScheduleStatus;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\Schedule;
use App\Models\ShiftAssignment;
use App\Models\ShiftType;
use App\Models\User;
use Illuminate\Support\Facades\Http;

/**
 * Edição manual de células da grelha em DRAFT com revalidação pelo solver
 * (issue #12, ADR-0002). O Laravel nunca decide se a alteração é válida —
 * monta a escala hipotética completa e delega ao POST /validate.
 */
beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->admin = User::factory()->admin()->inOrganization($this->org)->create();

    $this->shiftM = ShiftType::factory()->for($this->org)->create();
    $this->shiftT = ShiftType::factory()->tarde()->for($this->org)->create();
    $this->shiftN = ShiftType::factory()->noite()->for($this->org)->create();

    $this->schedule = Schedule::factory()->for($this->org)->create([
        'period_start' => '2026-08-01',
        'period_end' => '2026-08-31',
        'status' => ScheduleStatus::Draft,
    ]);

    $this->ana = Employee::factory()->for($this->org)->create(['name' => 'Ana']);
    $this->beatriz = Employee::factory()->for($this->org)->create(['name' => 'Beatriz']);

    ShiftAssignment::factory()->create([
        'schedule_id' => $this->schedule->id,
        'employee_id' => $this->ana->id,
        'date' => '2026-08-14',
        'shift_type_id' => $this->shiftN->id,
        'origin' => AssignmentOrigin::Generated,
    ]);
    ShiftAssignment::factory()->create([
        'schedule_id' => $this->schedule->id,
        'employee_id' => $this->beatriz->id,
        'date' => '2026-08-14',
        'shift_type_id' => $this->shiftM->id,
        'origin' => AssignmentOrigin::Generated,
    ]);
});

function cellUrl(Schedule $schedule): string
{
    return "/admin/escalas/{$schedule->id}/celula";
}

test('a valid cell edit persists with MANUAL origin and sends the full hypothetical schedule to the solver', function () {
    Http::fake([
        '*/validate' => Http::response(['valid' => true, 'violations' => []], 200),
    ]);

    $this->actingAs($this->admin)
        ->patch(cellUrl($this->schedule), [
            'employee_id' => $this->beatriz->id,
            'date' => '2026-08-14',
            'shift_type_id' => $this->shiftT->id,
        ])
        ->assertRedirect();

    $assignment = ShiftAssignment::where('schedule_id', $this->schedule->id)
        ->where('employee_id', $this->beatriz->id)
        ->whereDate('date', '2026-08-14')
        ->first();

    expect($assignment->shift_type_id)->toBe($this->shiftT->id)
        ->and($assignment->origin)->toBe(AssignmentOrigin::Manual);

    Http::assertSent(function ($request) {
        if (! str_ends_with($request->url(), '/validate')) {
            return false;
        }

        $assignments = collect($request->data()['assignments']);

        $changed = $assignments->first(fn ($a) => $a['employee_id'] === $this->beatriz->id && $a['date'] === '2026-08-14');
        $untouched = $assignments->first(fn ($a) => $a['employee_id'] === $this->ana->id && $a['date'] === '2026-08-14');

        return $changed !== null && $changed['shift'] === 'T'
            && $untouched !== null && $untouched['shift'] === 'N'
            && $assignments->count() === 2;
    });
});

test('a cell edit rejected by the solver is not persisted and the violation is returned', function () {
    Http::fake([
        '*/validate' => Http::response([
            'valid' => false,
            'violations' => [
                ['rule' => 'H3', 'message' => 'descanso 11h: N→M', 'date' => '2026-08-15', 'employee_id' => null],
            ],
        ], 200),
    ]);

    $response = $this->actingAs($this->admin)
        ->from("/admin/escalas/{$this->schedule->id}")
        ->patch(cellUrl($this->schedule), [
            'employee_id' => $this->ana->id,
            'date' => '2026-08-15',
            'shift_type_id' => $this->shiftM->id,
        ]);

    $response->assertRedirect("/admin/escalas/{$this->schedule->id}");
    $response->assertSessionHas('cell_violations', function ($violations) {
        return $violations[0]['rule'] === 'H3';
    });

    expect(ShiftAssignment::where('schedule_id', $this->schedule->id)
        ->where('employee_id', $this->ana->id)
        ->whereDate('date', '2026-08-15')
        ->exists())->toBeFalse();
});

test('editing a cell of a published schedule is rejected', function () {
    Http::fake();

    $this->schedule->update(['status' => ScheduleStatus::Published]);

    $this->actingAs($this->admin)
        ->patch(cellUrl($this->schedule), [
            'employee_id' => $this->ana->id,
            'date' => '2026-08-14',
            'shift_type_id' => $this->shiftM->id,
        ])
        ->assertStatus(400);
});

test('editing a cell with an employee from another organization fails', function () {
    $otherOrg = Organization::factory()->create();
    $otherEmployee = Employee::factory()->for($otherOrg)->create();

    Http::fake([
        '*/validate' => Http::response(['valid' => true, 'violations' => []], 200),
    ]);

    $this->actingAs($this->admin)
        ->patch(cellUrl($this->schedule), [
            'employee_id' => $otherEmployee->id,
            'date' => '2026-08-14',
            'shift_type_id' => $this->shiftM->id,
        ])
        ->assertStatus(404);

    Http::assertNothingSent();
});

test('editing a cell with a shift type from another organization fails', function () {
    Http::fake();

    $otherOrg = Organization::factory()->create();
    $otherShiftType = ShiftType::factory()->for($otherOrg)->create();

    $this->actingAs($this->admin)
        ->patch(cellUrl($this->schedule), [
            'employee_id' => $this->ana->id,
            'date' => '2026-08-14',
            'shift_type_id' => $otherShiftType->id,
        ])
        ->assertStatus(404);
});

test('when the solver is down the edit is not persisted and a clear error is returned', function () {
    Http::fake([
        '*/validate' => Http::response('solver crashed', 500),
    ]);

    $response = $this->actingAs($this->admin)
        ->from("/admin/escalas/{$this->schedule->id}")
        ->patch(cellUrl($this->schedule), [
            'employee_id' => $this->ana->id,
            'date' => '2026-08-14',
            'shift_type_id' => $this->shiftM->id,
        ]);

    $response->assertRedirect("/admin/escalas/{$this->schedule->id}");
    $response->assertSessionHas('cell_error');

    $assignment = ShiftAssignment::where('schedule_id', $this->schedule->id)
        ->where('employee_id', $this->ana->id)
        ->whereDate('date', '2026-08-14')
        ->first();

    expect($assignment->shift_type_id)->toBe($this->shiftN->id);
});

test('an employee cannot edit schedule cells', function () {
    Http::fake();

    $employee = User::factory()->inOrganization($this->org)->create();

    $this->actingAs($employee)
        ->patch(cellUrl($this->schedule), [
            'employee_id' => $this->ana->id,
            'date' => '2026-08-14',
            'shift_type_id' => $this->shiftM->id,
        ])
        ->assertForbidden();
});
