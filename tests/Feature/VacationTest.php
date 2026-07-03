<?php

use App\Enums\AssignmentOrigin;
use App\Enums\VacationStatus;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\Schedule;
use App\Models\ShiftAssignment;
use App\Models\ShiftType;
use App\Models\User;
use App\Models\VacationRequest;
use App\Notifications\VacationDecided;
use App\Notifications\VacationRequested;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Notification::fake();

    $this->org = Organization::factory()->create();
    $this->admin = User::factory()->admin()->inOrganization($this->org)->create();

    $this->employeeUser = User::factory()->inOrganization($this->org)->create();
    $this->employee = Employee::factory()->for($this->org)->create(['user_id' => $this->employeeUser->id]);

    $this->start = now()->addMonths(2)->startOfMonth()->toDateString();
    $this->end = now()->addMonths(2)->startOfMonth()->addDays(2)->toDateString();
});

test('requesting vacation with a published schedule covering the period stores the solver impact and notifies admins', function () {
    $schedule = Schedule::factory()->for($this->org)->published()->create([
        'period_start' => now()->addMonths(2)->startOfMonth()->toDateString(),
        'period_end' => now()->addMonths(2)->endOfMonth()->toDateString(),
    ]);

    Http::fake([
        '*/vacation-impact' => Http::response([
            'ok' => false,
            'issues' => [
                ['rule' => 'H1', 'message' => 'Faltam 1 turno M no dia', 'date' => $this->start, 'employee_id' => $this->employee->id],
            ],
        ], 200),
    ]);

    $this->actingAs($this->employeeUser)->post('/ferias', [
        'start_date' => $this->start,
        'end_date' => $this->end,
    ])->assertRedirect();

    $vacation = VacationRequest::sole();

    expect($vacation->employee_id)->toBe($this->employee->id)
        ->and($vacation->status)->toBe(VacationStatus::Pending)
        ->and($vacation->impact['ok'])->toBeFalse()
        ->and($vacation->impact['issues'])->toHaveCount(1);

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/vacation-impact')
        && $request->data()['employee_id'] === $this->employee->id
        && $request->data()['start'] === $this->start
        && $request->data()['end'] === $this->end
        && $request->data()['assignments'] !== null);

    Notification::assertSentTo($this->admin, VacationRequested::class);
    Notification::assertNotSentTo($this->employeeUser, VacationRequested::class);
});

test('requesting vacation without a published schedule covering the period does not call the solver', function () {
    Http::fake();

    $this->actingAs($this->employeeUser)->post('/ferias', [
        'start_date' => $this->start,
        'end_date' => $this->end,
    ])->assertRedirect();

    $vacation = VacationRequest::sole();

    expect($vacation->impact['ok'])->toBeTrue()
        ->and($vacation->impact['issues'])->toBe([])
        ->and($vacation->impact['no_schedule'])->toBeTrue();

    Http::assertNothingSent();

    Notification::assertSentTo($this->admin, VacationRequested::class);
});

test('overlapping vacation request is rejected', function () {
    Http::fake();

    VacationRequest::factory()->for($this->org)->create([
        'employee_id' => $this->employee->id,
        'start_date' => $this->start,
        'end_date' => $this->end,
        'status' => VacationStatus::Pending,
    ]);

    $this->actingAs($this->employeeUser)->post('/ferias', [
        'start_date' => $this->start,
        'end_date' => now()->addMonths(2)->startOfMonth()->addDays(5)->toDateString(),
    ])->assertSessionHasErrors('start_date');

    expect(VacationRequest::count())->toBe(1);
});

test('approving a vacation request marks the covered days as day off with origin VACATION and notifies the employee', function () {
    $schedule = Schedule::factory()->for($this->org)->published()->create([
        'period_start' => now()->addMonths(2)->startOfMonth()->toDateString(),
        'period_end' => now()->addMonths(2)->endOfMonth()->toDateString(),
    ]);

    $shiftM = ShiftType::factory()->for($this->org)->create();

    $coveredAssignment = ShiftAssignment::factory()->create([
        'schedule_id' => $schedule->id,
        'employee_id' => $this->employee->id,
        'date' => $this->start,
        'shift_type_id' => $shiftM->id,
        'origin' => AssignmentOrigin::Generated,
    ]);

    $outsideAssignment = ShiftAssignment::factory()->create([
        'schedule_id' => $schedule->id,
        'employee_id' => $this->employee->id,
        'date' => now()->addMonths(2)->endOfMonth()->toDateString(),
        'shift_type_id' => $shiftM->id,
        'origin' => AssignmentOrigin::Generated,
    ]);

    $vacation = VacationRequest::factory()->for($this->org)->create([
        'employee_id' => $this->employee->id,
        'start_date' => $this->start,
        'end_date' => $this->end,
        'status' => VacationStatus::Pending,
    ]);

    $this->actingAs($this->admin)
        ->post("/admin/ferias/{$vacation->id}/aprovar")
        ->assertRedirect();

    expect($vacation->fresh()->status)->toBe(VacationStatus::Approved)
        ->and($vacation->fresh()->decided_by)->toBe($this->admin->id);

    $coveredAssignment->refresh();
    expect($coveredAssignment->shift_type_id)->toBeNull()
        ->and($coveredAssignment->origin)->toBe(AssignmentOrigin::Vacation);

    $outsideAssignment->refresh();
    expect($outsideAssignment->shift_type_id)->toBe($shiftM->id)
        ->and($outsideAssignment->origin)->toBe(AssignmentOrigin::Generated);

    Notification::assertSentTo($this->employeeUser, VacationDecided::class);
});

test('declining a vacation request notifies the employee without touching assignments', function () {
    $vacation = VacationRequest::factory()->for($this->org)->create([
        'employee_id' => $this->employee->id,
        'start_date' => $this->start,
        'end_date' => $this->end,
        'status' => VacationStatus::Pending,
    ]);

    $this->actingAs($this->admin)
        ->post("/admin/ferias/{$vacation->id}/recusar")
        ->assertRedirect();

    expect($vacation->fresh()->status)->toBe(VacationStatus::Declined);

    Notification::assertSentTo($this->employeeUser, VacationDecided::class);
});

test('an employee can cancel only her own pending vacation request', function () {
    $otherUser = User::factory()->inOrganization($this->org)->create();
    Employee::factory()->for($this->org)->create(['user_id' => $otherUser->id]);

    $vacation = VacationRequest::factory()->for($this->org)->create([
        'employee_id' => $this->employee->id,
        'status' => VacationStatus::Pending,
    ]);

    $this->actingAs($otherUser)
        ->post("/ferias/{$vacation->id}/cancelar")
        ->assertForbidden();

    $this->actingAs($this->employeeUser)
        ->post("/ferias/{$vacation->id}/cancelar")
        ->assertRedirect();

    expect($vacation->fresh()->status)->toBe(VacationStatus::Cancelled);

    $vacation->update(['status' => VacationStatus::Approved]);

    $this->actingAs($this->employeeUser)
        ->post("/ferias/{$vacation->id}/cancelar")
        ->assertStatus(400);
});

test('employee cannot access admin vacation routes', function () {
    $vacation = VacationRequest::factory()->for($this->org)->create(['employee_id' => $this->employee->id]);

    $this->actingAs($this->employeeUser)->get('/admin/ferias')->assertForbidden();
    $this->actingAs($this->employeeUser)->post("/admin/ferias/{$vacation->id}/aprovar")->assertForbidden();
    $this->actingAs($this->employeeUser)->post("/admin/ferias/{$vacation->id}/recusar")->assertForbidden();
});

test('vacation requests are tenant scoped', function () {
    $otherOrg = Organization::factory()->create();
    $otherAdmin = User::factory()->admin()->inOrganization($otherOrg)->create();
    $otherEmployee = Employee::factory()->for($otherOrg)->create();

    $vacation = VacationRequest::factory()->for($this->org)->create(['employee_id' => $this->employee->id]);
    VacationRequest::factory()->for($otherOrg)->create(['employee_id' => $otherEmployee->id]);

    $this->actingAs($this->admin)->get('/admin/ferias')->assertInertia(
        fn (Assert $page) => $page->component('admin/vacations/index')->has('vacations', 1)
    );

    $this->actingAs($otherAdmin)
        ->post("/admin/ferias/{$vacation->id}/aprovar")
        ->assertNotFound();
});
