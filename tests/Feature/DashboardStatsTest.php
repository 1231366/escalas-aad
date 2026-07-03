<?php

use App\Enums\ContractType;
use App\Enums\SwapStatus;
use App\Enums\VacationStatus;
use App\Models\Employee;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\Schedule;
use App\Models\ShiftAssignment;
use App\Models\ShiftType;
use App\Models\SwapRequest;
use App\Models\User;
use App\Models\VacationRequest;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

afterEach(function () {
    Carbon::setTestNow();
});

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->admin = User::factory()->admin()->inOrganization($this->org)->create();
    $this->shiftM = ShiftType::factory()->for($this->org)->create();
});

/**
 * Marca os turnos M de segunda a sexta (+ opcionalmente fim de semana) para
 * uma funcionária num período de 1 semana (2026-07-06 seg a 2026-07-12 dom).
 */
function assignWeek(ShiftType $shiftM, Schedule $schedule, Employee $employee, array $workDays): void
{
    $period = collect(range(6, 12))->map(fn (int $day) => sprintf('2026-07-%02d', $day));

    foreach ($period as $date) {
        ShiftAssignment::factory()->for($schedule)->for($employee, 'employee')->create([
            'date' => $date,
            'shift_type_id' => in_array($date, $workDays, true) ? $shiftM->id : null,
        ]);
    }
}

test('admin dashboard reports equity per employee for the last published schedule', function () {
    $schedule = Schedule::factory()->for($this->org)->published()->create([
        'period_start' => '2026-07-06',
        'period_end' => '2026-07-12',
    ]);

    $ana = Employee::factory()->for($this->org)->create(['name' => 'Ana', 'contract' => ContractType::H40]);
    $beatriz = Employee::factory()->for($this->org)->create(['name' => 'Beatriz', 'contract' => ContractType::H40]);

    // Ana: Seg-Sex trabalha (40h), fim de semana de folga.
    assignWeek($this->shiftM, $schedule, $ana, ['2026-07-06', '2026-07-07', '2026-07-08', '2026-07-09', '2026-07-10']);
    // Beatriz: os 7 dias, incluindo o fim de semana (56h).
    assignWeek($this->shiftM, $schedule, $beatriz, [
        '2026-07-06', '2026-07-07', '2026-07-08', '2026-07-09', '2026-07-10', '2026-07-11', '2026-07-12',
    ]);

    $response = $this->actingAs($this->admin)->get('/dashboard');

    $response->assertOk();
    $response->assertInertia(function (Assert $page) use ($ana, $beatriz) {
        $page->component('dashboard')
            ->has('admin_stats.equity.employees', 2)
            ->where('admin_stats.equity.max_hours_employee_id', $beatriz->id)
            ->where('admin_stats.equity.min_hours_employee_id', $ana->id);

        $employees = collect($page->toArray()['props']['admin_stats']['equity']['employees']);

        $anaRow = $employees->firstWhere('employee_id', $ana->id);
        $beatrizRow = $employees->firstWhere('employee_id', $beatriz->id);

        // valores JSON perdem a distinção int/float no round-trip (ver TenantScopingTest) — toEqual.
        expect($anaRow['total_hours'])->toEqual(40)
            ->and($anaRow['weekends_worked'])->toEqual(0)
            ->and($anaRow['days_off'])->toEqual(2)
            ->and($anaRow['hour_bank_balance'])->toEqual(0)
            ->and($anaRow['hour_bank_label'])->toBe('+0h');

        expect($beatrizRow['total_hours'])->toEqual(56)
            ->and($beatrizRow['weekends_worked'])->toEqual(2)
            ->and($beatrizRow['days_off'])->toEqual(0)
            ->and($beatrizRow['hour_bank_balance'])->toEqual(16)
            ->and($beatrizRow['hour_bank_label'])->toBe('+16h');
    });
});

test('hour bank balance compares worked hours against the weekly contract (H37_30 vs H40)', function () {
    $schedule = Schedule::factory()->for($this->org)->published()->create([
        'period_start' => '2026-07-06',
        'period_end' => '2026-07-12',
    ]);

    $carla = Employee::factory()->for($this->org)->create(['name' => 'Carla', 'contract' => ContractType::H37_30]);
    // Trabalha Seg-Sex (40h) com contrato de 37h30 => saldo +2.5h.
    assignWeek($this->shiftM, $schedule, $carla, ['2026-07-06', '2026-07-07', '2026-07-08', '2026-07-09', '2026-07-10']);

    $response = $this->actingAs($this->admin)->get('/dashboard');

    $response->assertInertia(function (Assert $page) use ($carla) {
        $employees = collect($page->toArray()['props']['admin_stats']['equity']['employees']);
        $carlaRow = $employees->firstWhere('employee_id', $carla->id);

        expect($carlaRow['contractual_hours'])->toEqual(37.5)
            ->and($carlaRow['hour_bank_balance'])->toEqual(2.5)
            ->and($carlaRow['hour_bank_label'])->toBe('+2.5h');
    });
});

test('admin dashboard shows an empty state when there is no published schedule yet', function () {
    $response = $this->actingAs($this->admin)->get('/dashboard');

    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->where('admin_stats.equity', null));
});

test('admin dashboard counts pending swap and vacation requests and pending invitations', function () {
    $employeeA = Employee::factory()->for($this->org)->create();
    $employeeB = Employee::factory()->for($this->org)->create();

    // Todas as chaves estrangeiras da factory de SwapRequest são fornecidas
    // explicitamente para não deixar a factory criar a sua própria organização/
    // escala internas (o que criaria escalas duplicadas e violaria a
    // constraint única (organization_id, period_start, period_end)).
    $swapSchedule = Schedule::factory()->for($this->org)->create();
    $assignmentA = ShiftAssignment::factory()->for($swapSchedule)->for($employeeA, 'employee')->create();
    $assignmentB = ShiftAssignment::factory()->for($swapSchedule)->for($employeeB, 'employee')->create();

    foreach ([SwapStatus::Pending, SwapStatus::Accepted, SwapStatus::Declined] as $status) {
        SwapRequest::factory()->create([
            'organization_id' => $this->org->id,
            'schedule_id' => $swapSchedule->id,
            'requester_employee_id' => $employeeA->id,
            'target_employee_id' => $employeeB->id,
            'requester_assignment_id' => $assignmentA->id,
            'target_assignment_id' => $assignmentB->id,
            'status' => $status,
        ]);
    }

    VacationRequest::factory()->for($this->org)->for($employeeA)->create(['status' => VacationStatus::Pending]);
    VacationRequest::factory()->for($this->org)->for($employeeB)->create(['status' => VacationStatus::Approved]);

    Invitation::factory()->for($this->org)->create(); // pending
    Invitation::factory()->for($this->org)->expired()->create();
    Invitation::factory()->for($this->org)->revoked()->create();
    Invitation::factory()->for($this->org)->create(['accepted_at' => now()]);

    $response = $this->actingAs($this->admin)->get('/dashboard');

    $response->assertInertia(fn (Assert $page) => $page
        ->where('admin_stats.this_month.pending_swaps', 2)
        ->where('admin_stats.this_month.pending_vacations', 1)
        ->where('admin_stats.this_month.pending_invitations', 1));
});

test('admin dashboard reports the current month schedule status', function () {
    Carbon::setTestNow('2026-07-15');

    $schedule = Schedule::factory()->for($this->org)->create([
        'period_start' => '2026-07-01',
        'period_end' => '2026-07-31',
    ]);

    $response = $this->actingAs($this->admin)->get('/dashboard');

    $response->assertInertia(fn (Assert $page) => $page
        ->where('admin_stats.this_month.schedule.id', $schedule->id)
        ->where('admin_stats.this_month.schedule.status', 'DRAFT'));
});

test('employee dashboard shows her next shift, current week and pending requests, not the equity card', function () {
    Carbon::setTestNow('2026-07-07 09:00:00');

    $schedule = Schedule::factory()->for($this->org)->published()->create([
        'period_start' => '2026-07-06',
        'period_end' => '2026-07-12',
    ]);

    $user = User::factory()->inOrganization($this->org)->create();
    $employee = Employee::factory()->for($this->org)->create(['user_id' => $user->id]);
    $otherEmployee = Employee::factory()->for($this->org)->create();

    // Hoje (07-07) é folga; o próximo turno é 07-08 (M).
    ShiftAssignment::factory()->for($schedule)->for($employee, 'employee')->create(['date' => '2026-07-06', 'shift_type_id' => null]);
    ShiftAssignment::factory()->for($schedule)->for($employee, 'employee')->create(['date' => '2026-07-07', 'shift_type_id' => null]);
    ShiftAssignment::factory()->for($schedule)->for($employee, 'employee')->create(['date' => '2026-07-08', 'shift_type_id' => $this->shiftM->id]);

    $otherAssignment = ShiftAssignment::factory()->for($schedule)->for($otherEmployee, 'employee')
        ->create(['date' => '2026-07-09', 'shift_type_id' => $this->shiftM->id]);
    $ownAssignment = ShiftAssignment::factory()->for($schedule)->for($employee, 'employee')
        ->create(['date' => '2026-07-10', 'shift_type_id' => $this->shiftM->id]);

    // Todas as FK explícitas para não deixar a factory criar a sua própria
    // organização/escala internas (ver comentário na contagem de pedidos).
    SwapRequest::factory()->create([
        'organization_id' => $this->org->id,
        'schedule_id' => $schedule->id,
        'requester_employee_id' => $employee->id,
        'target_employee_id' => $otherEmployee->id,
        'requester_assignment_id' => $ownAssignment->id,
        'target_assignment_id' => $otherAssignment->id,
        'status' => SwapStatus::Pending,
    ]);
    VacationRequest::factory()->for($this->org)->for($employee)->create(['status' => VacationStatus::Pending]);

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk();
    $response->assertInertia(function (Assert $page) {
        $page->component('dashboard')
            ->where('admin_stats', null)
            ->where('employee_stats.next_shift.date', '2026-07-08')
            ->where('employee_stats.next_shift.shift_code', 'M')
            ->where('employee_stats.pending_swaps', 1)
            ->where('employee_stats.pending_vacations', 1);

        $props = $page->toArray()['props'];

        expect($props)->not->toHaveKey('viability_details_that_do_not_exist')
            ->and($props['admin_stats'])->toBeNull()
            ->and($props['employee_stats']['current_week'])->not->toBeEmpty();
    });
});
