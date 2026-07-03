<?php

use App\Enums\AssignmentOrigin;
use App\Enums\ScheduleStatus;
use App\Enums\SwapStatus;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\Schedule;
use App\Models\ShiftAssignment;
use App\Models\ShiftType;
use App\Models\SwapRequest;
use App\Models\User;
use App\Notifications\SwapApplied;
use App\Notifications\SwapAwaitingApproval;
use App\Notifications\SwapDeclined;
use App\Notifications\SwapRequested;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->org = Organization::factory()->create(['settings' => ['swap_requires_admin_approval' => false]]);
    $this->admin = User::factory()->admin()->inOrganization($this->org)->create();

    $this->morning = ShiftType::factory()->for($this->org)->create(['code' => 'M']);
    $this->night = ShiftType::factory()->tarde()->for($this->org)->create(['code' => 'T']);

    $this->schedule = Schedule::factory()->for($this->org)->create([
        'status' => ScheduleStatus::Published,
        'period_start' => '2026-09-01',
        'period_end' => '2026-09-05',
    ]);

    $this->aliceUser = User::factory()->inOrganization($this->org)->create();
    $this->alice = Employee::factory()->for($this->org)->create(['user_id' => $this->aliceUser->id, 'name' => 'Alice']);

    $this->beatrizUser = User::factory()->inOrganization($this->org)->create();
    $this->beatriz = Employee::factory()->for($this->org)->create(['user_id' => $this->beatrizUser->id, 'name' => 'Beatriz']);

    $this->aliceAssignment = ShiftAssignment::factory()->create([
        'schedule_id' => $this->schedule->id,
        'employee_id' => $this->alice->id,
        'date' => '2026-09-03',
        'shift_type_id' => $this->morning->id,
        'origin' => AssignmentOrigin::Generated,
    ]);

    $this->beatrizAssignment = ShiftAssignment::factory()->create([
        'schedule_id' => $this->schedule->id,
        'employee_id' => $this->beatriz->id,
        'date' => '2026-09-03',
        'shift_type_id' => $this->night->id,
        'origin' => AssignmentOrigin::Generated,
    ]);
});

test('employee sees swap candidates from the solver for her own future shift', function () {
    Http::fake([
        '*/swap-candidates' => Http::response([
            'candidates' => [['employee_id' => $this->beatriz->id, 'shift' => 'T']],
        ], 200),
    ]);

    $this->travelTo('2026-09-01');

    $response = $this->actingAs($this->aliceUser)->get("/trocas/nova/{$this->aliceAssignment->id}");

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page->component('swaps/create')->has('candidates', 1)->where('candidates.0.employee_id', $this->beatriz->id)
    );
});

test('employee cannot open the swap picker for a colleague shift', function () {
    $this->actingAs($this->aliceUser)->get("/trocas/nova/{$this->beatrizAssignment->id}")->assertForbidden();
});

test('creating a swap request notifies target and admins', function () {
    Notification::fake();

    Http::fake(['*/swap-candidates' => Http::response(['candidates' => [['employee_id' => $this->beatriz->id, 'shift' => 'T']]], 200)]);

    $this->travelTo('2026-09-01');

    $this->actingAs($this->aliceUser)->post('/trocas', [
        'requester_assignment_id' => $this->aliceAssignment->id,
        'target_employee_id' => $this->beatriz->id,
    ])->assertRedirect(route('swaps.index'));

    $swap = SwapRequest::first();
    expect($swap->status)->toBe(SwapStatus::Pending)
        ->and($swap->admin_approval_required)->toBeFalse();

    Notification::assertSentTo($this->beatrizUser, SwapRequested::class);
    Notification::assertSentTo($this->admin, SwapRequested::class);
});

test('a colleague no longer offered by the solver cannot be requested', function () {
    Http::fake(['*/swap-candidates' => Http::response(['candidates' => []], 200)]);

    $this->travelTo('2026-09-01');

    $this->actingAs($this->aliceUser)->post('/trocas', [
        'requester_assignment_id' => $this->aliceAssignment->id,
        'target_employee_id' => $this->beatriz->id,
    ])->assertStatus(422);

    expect(SwapRequest::count())->toBe(0);
});

test('accepting applies the swap immediately when the org does not require admin approval', function () {
    Notification::fake();

    $swap = SwapRequest::factory()->create([
        'organization_id' => $this->org->id,
        'schedule_id' => $this->schedule->id,
        'requester_employee_id' => $this->alice->id,
        'target_employee_id' => $this->beatriz->id,
        'requester_assignment_id' => $this->aliceAssignment->id,
        'target_assignment_id' => $this->beatrizAssignment->id,
        'status' => SwapStatus::Pending,
        'admin_approval_required' => false,
    ]);

    Http::fake(['*/validate' => Http::response(['valid' => true, 'violations' => []], 200)]);

    $this->actingAs($this->beatrizUser)->post("/trocas/{$swap->id}/aceitar")->assertRedirect(route('swaps.index'));

    $swap->refresh();
    expect($swap->status)->toBe(SwapStatus::Applied)
        ->and($this->aliceAssignment->fresh()->shift_type_id)->toBe($this->night->id)
        ->and($this->beatrizAssignment->fresh()->shift_type_id)->toBe($this->morning->id)
        ->and($this->aliceAssignment->fresh()->origin)->toBe(AssignmentOrigin::Swap);

    Notification::assertSentTo($this->aliceUser, SwapApplied::class);
    Notification::assertSentTo($this->beatrizUser, SwapApplied::class);
});

test('accepting when the org requires admin approval only marks accepted and notifies admins', function () {
    Notification::fake();
    $this->org->update(['settings' => ['swap_requires_admin_approval' => true]]);

    $swap = SwapRequest::factory()->create([
        'organization_id' => $this->org->id,
        'schedule_id' => $this->schedule->id,
        'requester_employee_id' => $this->alice->id,
        'target_employee_id' => $this->beatriz->id,
        'requester_assignment_id' => $this->aliceAssignment->id,
        'target_assignment_id' => $this->beatrizAssignment->id,
        'status' => SwapStatus::Pending,
        'admin_approval_required' => true,
    ]);

    Http::fake(['*/validate' => Http::response(['valid' => true, 'violations' => []], 200)]);

    $this->actingAs($this->beatrizUser)->post("/trocas/{$swap->id}/aceitar")->assertRedirect();

    expect($swap->fresh()->status)->toBe(SwapStatus::Accepted)
        ->and($this->aliceAssignment->fresh()->shift_type_id)->toBe($this->morning->id);

    Notification::assertSentTo($this->admin, SwapAwaitingApproval::class);

    $this->actingAs($this->admin)->post("/admin/trocas/{$swap->id}/aprovar")->assertRedirect();

    expect($swap->fresh()->status)->toBe(SwapStatus::Applied)
        ->and($this->aliceAssignment->fresh()->shift_type_id)->toBe($this->night->id);
});

test('revalidation failure on accept rejects the swap and explains why', function () {
    Notification::fake();

    $swap = SwapRequest::factory()->create([
        'organization_id' => $this->org->id,
        'schedule_id' => $this->schedule->id,
        'requester_employee_id' => $this->alice->id,
        'target_employee_id' => $this->beatriz->id,
        'requester_assignment_id' => $this->aliceAssignment->id,
        'target_assignment_id' => $this->beatrizAssignment->id,
        'status' => SwapStatus::Pending,
        'admin_approval_required' => false,
    ]);

    Http::fake(['*/validate' => Http::response([
        'valid' => false,
        'violations' => [['rule' => 'H3', 'message' => 'Descanso de 11h violado', 'date' => '2026-09-03', 'employee_id' => null]],
    ], 200)]);

    $this->actingAs($this->beatrizUser)->post("/trocas/{$swap->id}/aceitar")->assertRedirect();

    expect($swap->fresh()->status)->toBe(SwapStatus::Rejected)
        ->and($this->aliceAssignment->fresh()->shift_type_id)->toBe($this->morning->id);
});

test('declining notifies the requester and cannot be redone', function () {
    Notification::fake();

    $swap = SwapRequest::factory()->create([
        'organization_id' => $this->org->id,
        'schedule_id' => $this->schedule->id,
        'requester_employee_id' => $this->alice->id,
        'target_employee_id' => $this->beatriz->id,
        'requester_assignment_id' => $this->aliceAssignment->id,
        'target_assignment_id' => $this->beatrizAssignment->id,
        'status' => SwapStatus::Pending,
    ]);

    $this->actingAs($this->beatrizUser)->post("/trocas/{$swap->id}/recusar")->assertRedirect();

    expect($swap->fresh()->status)->toBe(SwapStatus::Declined);
    Notification::assertSentTo($this->aliceUser, SwapDeclined::class);

    $this->actingAs($this->beatrizUser)->post("/trocas/{$swap->id}/aceitar")->assertStatus(400);
});

test('only the target may accept or decline, only the requester may cancel', function () {
    $swap = SwapRequest::factory()->create([
        'organization_id' => $this->org->id,
        'schedule_id' => $this->schedule->id,
        'requester_employee_id' => $this->alice->id,
        'target_employee_id' => $this->beatriz->id,
        'requester_assignment_id' => $this->aliceAssignment->id,
        'target_assignment_id' => $this->beatrizAssignment->id,
        'status' => SwapStatus::Pending,
    ]);

    $this->actingAs($this->aliceUser)->post("/trocas/{$swap->id}/aceitar")->assertForbidden();
    $this->actingAs($this->beatrizUser)->post("/trocas/{$swap->id}/cancelar")->assertForbidden();
});

test('swap index is tenant scoped and lists sent and received separately', function () {
    $swap = SwapRequest::factory()->create([
        'organization_id' => $this->org->id,
        'schedule_id' => $this->schedule->id,
        'requester_employee_id' => $this->alice->id,
        'target_employee_id' => $this->beatriz->id,
        'requester_assignment_id' => $this->aliceAssignment->id,
        'target_assignment_id' => $this->beatrizAssignment->id,
        'status' => SwapStatus::Pending,
    ]);

    $this->actingAs($this->aliceUser)->get('/trocas')->assertInertia(
        fn ($page) => $page->component('swaps/index')->has('sent', 1)->has('received', 0)->where('sent.0.id', $swap->id)
    );

    $this->actingAs($this->beatrizUser)->get('/trocas')->assertInertia(
        fn ($page) => $page->has('sent', 0)->has('received', 1)
    );
});

test('admin without an employee profile sees empty swap lists instead of an error', function () {
    $this->actingAs($this->admin)->get('/trocas')->assertInertia(
        fn ($page) => $page->component('swaps/index')->has('sent', 0)->has('received', 0)
    );
});

test('non-employee cannot create a swap request', function () {
    $this->actingAs($this->admin)->post('/trocas', [
        'requester_assignment_id' => $this->aliceAssignment->id,
        'target_employee_id' => $this->beatriz->id,
    ])->assertForbidden();
});
