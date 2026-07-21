<?php

use App\Enums\Regime;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\User;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->admin = User::factory()->admin()->inOrganization($this->org)->create();
});

test('admin can create an employee without a user account', function () {
    $this->actingAs($this->admin)
        ->post('/admin/funcionarios', [
            'name' => 'Sofia Mock',
            'regime' => 'NOITE',
            'contract' => 'H37_30',
            'fixa_noite' => true,
        ])
        ->assertRedirect();

    $employee = Employee::where('name', 'Sofia Mock')->first();

    expect($employee)->not->toBeNull()
        ->and($employee->user_id)->toBeNull()
        ->and($employee->regime)->toBe(Regime::Noite)
        ->and($employee->active)->toBeTrue();
});

test('employee without account appears in the index as sem acesso', function () {
    Employee::factory()->for($this->org)->create(['name' => 'Ana Sem Acesso', 'user_id' => null]);

    $response = $this->actingAs($this->admin)->get('/admin/funcionarios');

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('admin/employees/index')
            ->where('employees.0.has_account', false)
    );
});

test('admin can edit a no-access employee profile', function () {
    $employee = Employee::factory()->for($this->org)->create(['user_id' => null, 'regime' => Regime::Dia]);

    $this->actingAs($this->admin)
        ->put("/admin/funcionarios/{$employee->id}", [
            'name' => $employee->name,
            'regime' => 'HIBRIDO',
            'contract' => 'H40',
            'fixa_noite' => false,
            'active' => false,
        ])
        ->assertRedirect();

    expect($employee->fresh()->regime)->toBe(Regime::Hibrido)
        ->and($employee->fresh()->active)->toBeFalse();
});

test('admin can delete a no-access employee', function () {
    $employee = Employee::factory()->for($this->org)->create(['user_id' => null]);

    $this->actingAs($this->admin)->delete("/admin/funcionarios/{$employee->id}")->assertRedirect();

    expect(Employee::find($employee->id))->toBeNull();
});

test('deleting an employee with an account also deletes the account', function () {
    $user = User::factory()->inOrganization($this->org)->create();
    $employee = Employee::factory()->for($this->org)->create(['user_id' => $user->id]);

    $this->actingAs($this->admin)->delete("/admin/funcionarios/{$employee->id}")->assertRedirect();

    expect(Employee::find($employee->id))->toBeNull()
        ->and(User::find($user->id))->toBeNull();
});

test('non-admin cannot manage employees', function () {
    $employee = User::factory()->inOrganization($this->org)->create();

    $this->actingAs($employee)->get('/admin/funcionarios')->assertForbidden();
    $this->actingAs($employee)->post('/admin/funcionarios', ['name' => 'X'])->assertForbidden();
});

test('employee list is tenant scoped', function () {
    $otherOrg = Organization::factory()->create();
    Employee::factory()->for($otherOrg)->create(['name' => 'Doutra Org']);
    Employee::factory()->for($this->org)->create(['name' => 'Desta Org']);

    $response = $this->actingAs($this->admin)->get('/admin/funcionarios');

    $response->assertInertia(fn ($page) => $page->has('employees', 1)->where('employees.0.name', 'Desta Org'));
});
