<?php

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->admin = User::factory()->admin()->inOrganization($this->org)->create();
    $this->employeeUser = User::factory()->inOrganization($this->org)->create();
});

test('admin sees the paginated audit log of their organization', function () {
    $this->actingAs($this->admin);

    $employee = Employee::factory()->for($this->org)->create(['name' => 'Sofia']);

    AuditLog::record('employee.updated', $employee, ['active' => false]);
    AuditLog::record('invitation.created', $employee, ['email' => 'sofia@example.com']);

    $response = $this->get('/admin/auditoria');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('admin/audit/index')
        ->has('logs.data', 2)
        ->where('logs.total', 2)
        ->has('actions', 2));

    $names = collect($response->viewData('page')['props']['logs']['data'])->pluck('actor_name')->unique();
    expect($names->all())->toBe([$this->admin->name]);
});

test('audit log lists 50 entries per page', function () {
    $this->actingAs($this->admin);

    Employee::factory()->for($this->org)->create();

    for ($i = 0; $i < 55; $i++) {
        AuditLog::record('rule.updated', null, ['i' => $i]);
    }

    $response = $this->get('/admin/auditoria');

    $response->assertInertia(fn (Assert $page) => $page
        ->has('logs.data', 50)
        ->where('logs.total', 55)
        ->where('logs.last_page', 2));

    $secondPage = $this->get('/admin/auditoria?page=2');
    $secondPage->assertInertia(fn (Assert $page) => $page->has('logs.data', 5));
});

test('audit log filters by action', function () {
    $this->actingAs($this->admin);

    AuditLog::record('schedule.published');
    AuditLog::record('schedule.published');
    AuditLog::record('invitation.created');

    $response = $this->get('/admin/auditoria?action=invitation.created');

    $response->assertInertia(fn (Assert $page) => $page
        ->has('logs.data', 1)
        ->where('filters.action', 'invitation.created'));

    $data = $response->viewData('page')['props']['logs']['data'];
    expect($data[0]['action'])->toBe('invitation.created');
});

test('audit log summarizes the changes as a readable JSON string', function () {
    $this->actingAs($this->admin);

    AuditLog::record('rule.updated', null, ['hour_bank_weekly_tolerance' => 6]);

    $response = $this->get('/admin/auditoria');

    $data = $response->viewData('page')['props']['logs']['data'];
    expect($data[0]['changes_summary'])->toBe('{"hour_bank_weekly_tolerance":6}');
});

test('non admin employees cannot view the audit log', function () {
    $this->actingAs($this->employeeUser)
        ->get('/admin/auditoria')
        ->assertForbidden();
});

test('audit log is tenant scoped: org B does not see org A logs', function () {
    $orgB = Organization::factory()->create();
    $adminB = User::factory()->admin()->inOrganization($orgB)->create();

    $this->actingAs($this->admin);
    AuditLog::record('schedule.published');

    $this->actingAs($adminB);
    AuditLog::record('invitation.created');

    $response = $this->actingAs($adminB)->get('/admin/auditoria');

    $response->assertInertia(fn (Assert $page) => $page->has('logs.data', 1));

    $data = $response->viewData('page')['props']['logs']['data'];
    expect($data[0]['action'])->toBe('invitation.created');
});
