<?php

use App\Models\Organization;
use App\Models\User;

test('user can log in end-to-end and reach the dashboard', function () {
    $org = Organization::factory()->create();
    $admin = User::factory()->admin()->inOrganization($org)->create([
        'email' => 'login-e2e@example.com',
        'password' => bcrypt('password123'),
    ]);

    $this->get('/login')->assertOk();

    $response = $this->post('/login', [
        'email' => 'login-e2e@example.com',
        'password' => 'password123',
    ]);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($admin);

    $this->get(route('dashboard'))->assertOk();
});

test('APP_URL includes an explicit port so links generated outside an HTTP request (queued mail) are reachable', function () {
    // Regressão: sem porta no APP_URL, o link do email de convite (enviado via fila,
    // fora de um pedido HTTP) resolvia para http://localhost (porta 80) em vez de :8000.
    expect(config('app.url'))->toMatch('/^https?:\/\/[^\/]+:\d+$/');
});
