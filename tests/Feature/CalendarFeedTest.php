<?php

use App\Models\Employee;
use App\Models\Organization;
use App\Models\Schedule;
use App\Models\ShiftAssignment;
use App\Models\ShiftType;
use App\Models\User;

beforeEach(function () {
    $this->org = Organization::factory()->create(['name' => 'Lar Bom Dia']);
    $this->user = User::factory()->inOrganization($this->org)->create();
    $this->employee = Employee::factory()->for($this->org)->create(['user_id' => $this->user->id]);
    $this->tarde = ShiftType::factory()->for($this->org)->tarde()->create();
    $this->manha = ShiftType::factory()->for($this->org)->create();
});

test('feed returns only shifts from published schedules, excluding day offs and draft schedules', function () {
    $published = Schedule::factory()->for($this->org)->published()->create([
        'period_start' => '2026-08-01',
        'period_end' => '2026-08-31',
    ]);
    $draft = Schedule::factory()->for($this->org)->create([
        'period_start' => '2026-09-01',
        'period_end' => '2026-09-30',
    ]);

    $this->user->regenerateCalendarToken();

    $shiftAssignment = ShiftAssignment::factory()->create([
        'schedule_id' => $published->id,
        'employee_id' => $this->employee->id,
        'date' => '2026-08-10',
        'shift_type_id' => $this->tarde->id,
    ]);

    ShiftAssignment::factory()->dayOff()->create([
        'schedule_id' => $published->id,
        'employee_id' => $this->employee->id,
        'date' => '2026-08-11',
    ]);

    ShiftAssignment::factory()->create([
        'schedule_id' => $draft->id,
        'employee_id' => $this->employee->id,
        'date' => '2026-08-12',
        'shift_type_id' => $this->manha->id,
    ]);

    $response = $this->get("/calendario/{$this->user->calendar_token}.ics");

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/calendar; charset=utf-8');

    $body = $response->getContent();

    expect($body)
        ->toContain("escalas-{$shiftAssignment->id}@escalas-aad")
        ->toContain('Turno Tarde')
        ->not->toContain('Turno Manhã');

    expect(substr_count($body, 'BEGIN:VEVENT'))->toBe(1);
});

test('shift crossing midnight produces correct start and end datetimes', function () {
    $published = Schedule::factory()->for($this->org)->published()->create();

    ShiftAssignment::factory()->create([
        'schedule_id' => $published->id,
        'employee_id' => $this->employee->id,
        'date' => '2026-08-10',
        'shift_type_id' => $this->tarde->id,
    ]);

    $this->user->regenerateCalendarToken();

    $body = $this->get("/calendario/{$this->user->calendar_token}.ics")->getContent();

    expect($body)
        ->toContain('DTSTART;TZID=Europe/Lisbon:20260810T160000')
        ->toContain('DTEND;TZID=Europe/Lisbon:20260811T000000');
});

test('night shift stays within the same day', function () {
    $published = Schedule::factory()->for($this->org)->published()->create();
    $noite = ShiftType::factory()->for($this->org)->noite()->create();

    ShiftAssignment::factory()->create([
        'schedule_id' => $published->id,
        'employee_id' => $this->employee->id,
        'date' => '2026-08-10',
        'shift_type_id' => $noite->id,
    ]);

    $this->user->regenerateCalendarToken();

    $body = $this->get("/calendario/{$this->user->calendar_token}.ics")->getContent();

    expect($body)
        ->toContain('DTSTART;TZID=Europe/Lisbon:20260810T000000')
        ->toContain('DTEND;TZID=Europe/Lisbon:20260810T080000');
});

test('invalid token returns 404', function () {
    $this->get('/calendario/token-invalido-que-nao-existe.ics')->assertNotFound();
});

test('regenerating the token invalidates the old feed url', function () {
    $oldToken = $this->user->regenerateCalendarToken();

    $this->get("/calendario/{$oldToken}.ics")->assertOk();

    $this->actingAs($this->user)->post('/settings/calendario/regenerar')->assertRedirect(route('calendar.edit'));

    $this->user->refresh();

    expect($this->user->calendar_token)->not->toBe($oldToken);

    $this->get("/calendario/{$oldToken}.ics")->assertNotFound();
    $this->get("/calendario/{$this->user->calendar_token}.ics")->assertOk();
});

test('settings calendar page shows the feed url for an authenticated user', function () {
    $response = $this->actingAs($this->user)->get('/settings/calendario');

    $response->assertOk();
    $this->user->refresh();

    $response->assertInertia(fn ($page) => $page
        ->component('settings/calendar')
        ->where('feed_url', route('calendar.feed', $this->user->calendar_token))
    );
});

test('guest is redirected to login when accessing calendar settings', function () {
    $this->get('/settings/calendario')->assertRedirect(route('login'));
});
