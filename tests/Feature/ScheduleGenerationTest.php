<?php

use App\Enums\AssignmentOrigin;
use App\Enums\ScheduleStatus;
use App\Events\SchedulePublished;
use App\Jobs\GenerateScheduleJob;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\Schedule;
use App\Models\ShiftAssignment;
use App\Models\ShiftType;
use App\Models\User;
use App\Services\Solver\SolverClient;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->admin = User::factory()->admin()->inOrganization($this->org)->create();

    $this->shiftM = ShiftType::factory()->for($this->org)->create();
    $this->shiftT = ShiftType::factory()->tarde()->for($this->org)->create();
    $this->shiftN = ShiftType::factory()->noite()->for($this->org)->create();
});

test('admin creating a schedule creates a draft and dispatches the generation job', function () {
    Queue::fake();

    $response = $this->actingAs($this->admin)->post('/admin/escalas', [
        'year' => 2026,
        'month' => 9,
    ]);

    $response->assertRedirect();

    $schedule = Schedule::sole();

    expect($schedule->status)->toBe(ScheduleStatus::Draft)
        ->and($schedule->period_start->toDateString())->toBe('2026-09-01')
        ->and($schedule->period_end->toDateString())->toBe('2026-09-30');

    Queue::assertPushed(GenerateScheduleJob::class, fn (GenerateScheduleJob $job) => $job->schedule->is($schedule));
});

test('creating a schedule for a period that already exists is rejected', function () {
    Schedule::factory()->for($this->org)->create([
        'period_start' => '2026-09-01',
        'period_end' => '2026-09-30',
    ]);

    $this->actingAs($this->admin)->post('/admin/escalas', [
        'year' => 2026,
        'month' => 9,
    ])->assertSessionHasErrors('month');
});

test('the job persists generated assignments and solver stats when FEASIBLE', function () {
    $schedule = Schedule::factory()->for($this->org)->create([
        'period_start' => '2026-09-01',
        'period_end' => '2026-09-03',
        'status' => ScheduleStatus::Draft,
    ]);

    $ana = Employee::factory()->for($this->org)->create();
    $beatriz = Employee::factory()->for($this->org)->create();

    Http::fake([
        '*/generate' => Http::response([
            'status' => 'FEASIBLE',
            'assignments' => [
                ['employee_id' => $ana->id, 'date' => '2026-09-01', 'shift' => 'M'],
                ['employee_id' => $ana->id, 'date' => '2026-09-02', 'shift' => null],
                ['employee_id' => $ana->id, 'date' => '2026-09-03', 'shift' => 'T'],
                ['employee_id' => $beatriz->id, 'date' => '2026-09-01', 'shift' => 'N'],
                ['employee_id' => $beatriz->id, 'date' => '2026-09-02', 'shift' => 'N'],
                ['employee_id' => $beatriz->id, 'date' => '2026-09-03', 'shift' => null],
            ],
            'objective' => 12.5,
            'wall_time_s' => 0.42,
        ], 200),
    ]);

    (new GenerateScheduleJob($schedule))->handle(app(SolverClient::class));

    $schedule->refresh();

    expect($schedule->solver_stats['status'])->toBe('FEASIBLE')
        ->and($schedule->solver_stats['objective'])->toBe(12.5)
        ->and($schedule->generated_at)->not->toBeNull();

    expect(ShiftAssignment::where('schedule_id', $schedule->id)->count())->toBe(6);

    $dayOff = ShiftAssignment::where([
        'schedule_id' => $schedule->id, 'employee_id' => $ana->id, 'date' => '2026-09-02',
    ])->first();
    expect($dayOff->shift_type_id)->toBeNull()
        ->and($dayOff->origin)->toBe(AssignmentOrigin::Generated);

    $morningShift = ShiftAssignment::where([
        'schedule_id' => $schedule->id, 'employee_id' => $ana->id, 'date' => '2026-09-01',
    ])->first();
    expect($morningShift->shiftType->code)->toBe('M');
});

test('the job records conflicts without touching assignments when INFEASIBLE', function () {
    $schedule = Schedule::factory()->for($this->org)->create(['status' => ScheduleStatus::Draft]);

    // atribuição pré-existente que não deve ser tocada por um resultado infeasible
    $preexisting = ShiftAssignment::factory()->create(['schedule_id' => $schedule->id]);

    Http::fake([
        '*/generate' => Http::response([
            'status' => 'INFEASIBLE',
            'assignments' => [],
            'conflicts' => [
                ['rule' => 'H1', 'message' => 'Faltam 2 turnos N no dia 14', 'date' => '2026-09-14', 'employee_id' => null],
            ],
        ], 200),
    ]);

    (new GenerateScheduleJob($schedule))->handle(app(SolverClient::class));

    $schedule->refresh();

    expect($schedule->solver_stats['status'])->toBe('INFEASIBLE')
        ->and($schedule->solver_stats['conflicts'])->toHaveCount(1)
        ->and($schedule->solver_stats['conflicts'][0]['rule'])->toBe('H1')
        ->and($schedule->generated_at)->toBeNull();

    expect(ShiftAssignment::where('schedule_id', $schedule->id)->pluck('id')->all())->toBe([$preexisting->id]);
});

test('publishing a draft schedule transitions state, fires the event and audits', function () {
    Event::fake([SchedulePublished::class]);

    $schedule = Schedule::factory()->for($this->org)->create(['status' => ScheduleStatus::Draft]);

    $this->actingAs($this->admin)
        ->post("/admin/escalas/{$schedule->id}/publicar")
        ->assertRedirect();

    $schedule->refresh();

    expect($schedule->status)->toBe(ScheduleStatus::Published)
        ->and($schedule->published_at)->not->toBeNull();

    Event::assertDispatched(SchedulePublished::class, fn (SchedulePublished $event) => $event->schedule->is($schedule));

    expect(AuditLog::where('action', 'schedule.published')->where('subject_id', $schedule->id)->exists())->toBeTrue();
});

test('reverting a published schedule to draft clears published_at and allows regenerating', function () {
    $schedule = Schedule::factory()->for($this->org)->create([
        'status' => ScheduleStatus::Published,
        'published_at' => now(),
    ]);

    $this->actingAs($this->admin)
        ->post("/admin/escalas/{$schedule->id}/repor-rascunho")
        ->assertRedirect();

    $schedule->refresh();

    expect($schedule->status)->toBe(ScheduleStatus::Draft)
        ->and($schedule->published_at)->toBeNull();

    expect(AuditLog::where('action', 'schedule.reverted_to_draft')->where('subject_id', $schedule->id)->exists())->toBeTrue();
});

test('reverting a draft schedule to draft is rejected', function () {
    $schedule = Schedule::factory()->for($this->org)->create(['status' => ScheduleStatus::Draft]);

    $this->actingAs($this->admin)
        ->post("/admin/escalas/{$schedule->id}/repor-rascunho")
        ->assertStatus(400);
});

test('admin can delete a schedule regardless of status, cascading its assignments', function () {
    $schedule = Schedule::factory()->for($this->org)->create(['status' => ScheduleStatus::Published]);
    $employee = Employee::factory()->for($this->org)->create();

    ShiftAssignment::factory()->for($schedule)->for($employee)->create();

    expect(ShiftAssignment::where('schedule_id', $schedule->id)->count())->toBe(1);

    $this->actingAs($this->admin)
        ->delete("/admin/escalas/{$schedule->id}")
        ->assertRedirect('/admin/escalas');

    expect(Schedule::find($schedule->id))->toBeNull()
        ->and(ShiftAssignment::where('schedule_id', $schedule->id)->count())->toBe(0);
});

test('employee cannot access admin schedule routes', function () {
    $employee = User::factory()->inOrganization($this->org)->create();

    $this->actingAs($employee)->get('/admin/escalas')->assertForbidden();
    $this->actingAs($employee)->post('/admin/escalas', ['year' => 2026, 'month' => 9])->assertForbidden();
});

test('a draft schedule for the current month is invisible to the employee', function () {
    $user = User::factory()->inOrganization($this->org)->create();
    Employee::factory()->for($this->org)->create(['user_id' => $user->id]);

    Schedule::factory()->for($this->org)->create([
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
        'status' => ScheduleStatus::Draft,
    ]);

    $this->actingAs($user)->get('/escala')->assertInertia(fn (Assert $page) => $page
        ->component('schedule/my')
        ->where('schedule', null)
    );
});

test('employee sees the published schedule of the current month at /escala', function () {
    $user = User::factory()->inOrganization($this->org)->create();
    $employee = Employee::factory()->for($this->org)->create(['user_id' => $user->id]);

    $schedule = Schedule::factory()->for($this->org)->create([
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
        'status' => ScheduleStatus::Published,
    ]);

    ShiftAssignment::factory()->create([
        'schedule_id' => $schedule->id,
        'employee_id' => $employee->id,
        'date' => now()->startOfMonth()->toDateString(),
        'shift_type_id' => $this->shiftM->id,
    ]);

    $this->actingAs($user)->get('/escala')->assertInertia(fn (Assert $page) => $page
        ->component('schedule/my')
        ->where('schedule.id', $schedule->id)
        ->has('employees')
    );
});

test('the solver payload builds initial_state from the last 7 days of the previous published schedule', function () {
    Http::fake([
        '*/generate' => Http::response(['status' => 'FEASIBLE', 'assignments' => []], 200),
    ]);

    $previous = Schedule::factory()->for($this->org)->create([
        'period_start' => '2026-08-01',
        'period_end' => '2026-08-31',
        'status' => ScheduleStatus::Published,
    ]);

    $ana = Employee::factory()->for($this->org)->create();

    // Fora da janela dos últimos 7 dias (cutoff = 31 ago - 6 = 25 ago).
    ShiftAssignment::factory()->create([
        'schedule_id' => $previous->id, 'employee_id' => $ana->id, 'date' => '2026-08-20', 'shift_type_id' => $this->shiftM->id,
    ]);
    // Dentro da janela.
    ShiftAssignment::factory()->create([
        'schedule_id' => $previous->id, 'employee_id' => $ana->id, 'date' => '2026-08-25', 'shift_type_id' => $this->shiftM->id,
    ]);
    ShiftAssignment::factory()->create([
        'schedule_id' => $previous->id, 'employee_id' => $ana->id, 'date' => '2026-08-31', 'shift_type_id' => $this->shiftT->id,
    ]);

    $current = Schedule::factory()->for($this->org)->create([
        'period_start' => '2026-09-01',
        'period_end' => '2026-09-30',
        'status' => ScheduleStatus::Draft,
    ]);

    app(SolverClient::class)->generate($current);

    Http::assertSent(function ($request) use ($ana) {
        if (! str_ends_with($request->url(), '/generate')) {
            return false;
        }

        $initialState = collect($request->data()['initial_state']);
        $dates = $initialState->where('employee_id', $ana->id)->pluck('date')->all();

        return in_array('2026-08-25', $dates, true)
            && in_array('2026-08-31', $dates, true)
            && ! in_array('2026-08-20', $dates, true)
            && $request->data()['period_start'] === '2026-09-01';
    });
});
