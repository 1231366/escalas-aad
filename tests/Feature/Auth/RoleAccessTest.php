<?php

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware(['web', 'auth', 'admin'])->get('/_test/admin-only', fn () => 'ok');
});

test('open registration is disabled', function () {
    $this->get('/register')->assertNotFound();
    $this->post('/register', [])->assertNotFound();
});

test('admin can access admin-only routes', function () {
    $org = Organization::factory()->create();
    $admin = User::factory()->admin()->inOrganization($org)->create();

    $this->actingAs($admin)->get('/_test/admin-only')->assertOk();
});

test('employee is forbidden from admin-only routes', function () {
    $org = Organization::factory()->create();
    $employee = User::factory()->inOrganization($org)->create();

    $this->actingAs($employee)->get('/_test/admin-only')->assertForbidden();
});

test('guest is redirected to login on admin-only routes', function () {
    $this->get('/_test/admin-only')->assertRedirect(route('login'));
});
