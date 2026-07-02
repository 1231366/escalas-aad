<?php

use App\Events\SchedulePublished;
use App\Models\Organization;
use App\Models\Schedule;
use App\Models\User;
use App\Notifications\SchedulePublishedNotification;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->otherOrg = Organization::factory()->create();
});

test('publishing a schedule notifies users of the same organization only', function () {
    Notification::fake();

    $employee = User::factory()->inOrganization($this->org)->create();
    $admin = User::factory()->admin()->inOrganization($this->org)->create();
    $outsider = User::factory()->inOrganization($this->otherOrg)->create();

    $schedule = Schedule::factory()->for($this->org)->published()->create();

    event(new SchedulePublished($schedule));

    Notification::assertSentTo([$employee, $admin], SchedulePublishedNotification::class);
    Notification::assertNotSentTo($outsider, SchedulePublishedNotification::class);
});

test('schedule published notification skips mail when the user opted out', function () {
    $optedOut = User::factory()->inOrganization($this->org)->create([
        'notification_prefs' => ['email' => ['schedule_published' => false]],
    ]);
    $optedIn = User::factory()->inOrganization($this->org)->create();

    $schedule = Schedule::factory()->for($this->org)->published()->create();
    $notification = new SchedulePublishedNotification($schedule);

    expect($notification->via($optedOut))->toBe(['database'])
        ->and($notification->via($optedIn))->toBe(['database', 'mail']);
});

test('schedule published notification message contains the month in full portuguese', function () {
    $schedule = Schedule::factory()->for($this->org)->published()->create([
        'period_start' => '2026-07-01',
    ]);

    $employee = User::factory()->inOrganization($this->org)->create();

    $data = (new SchedulePublishedNotification($schedule))->toArray($employee);

    expect($data['type'])->toBe('schedule_published')
        ->and($data['message'])->toBe('A escala de julho de 2026 já está publicada.')
        ->and($data['schedule_id'])->toBe($schedule->id)
        ->and($data['period_start'])->toBe('2026-07-01');
});

test('GET /notificacoes returns the unread count and the latest notifications', function () {
    $user = User::factory()->inOrganization($this->org)->create();
    $schedule = Schedule::factory()->for($this->org)->published()->create();

    $user->notify(new SchedulePublishedNotification($schedule));
    $user->notify(new SchedulePublishedNotification($schedule));

    $response = $this->actingAs($user)->getJson('/notificacoes')->assertOk();

    $response->assertJsonPath('unread_count', 2)
        ->assertJsonCount(2, 'notifications');

    expect($response->json('notifications.0'))
        ->toHaveKeys(['id', 'data', 'read_at', 'created_at']);
});

test('marking a single notification as read works and is scoped to the owner', function () {
    $user = User::factory()->inOrganization($this->org)->create();
    $otherUser = User::factory()->inOrganization($this->org)->create();
    $schedule = Schedule::factory()->for($this->org)->published()->create();

    $user->notify(new SchedulePublishedNotification($schedule));
    $otherUser->notify(new SchedulePublishedNotification($schedule));

    $notification = $user->notifications()->first();
    $othersNotification = $otherUser->notifications()->first();

    $this->actingAs($user)
        ->postJson("/notificacoes/{$notification->id}/lida")
        ->assertOk();

    expect($notification->fresh()->read_at)->not->toBeNull();

    $this->actingAs($user)
        ->postJson("/notificacoes/{$othersNotification->id}/lida")
        ->assertNotFound();
});

test('marking all notifications as read works', function () {
    $user = User::factory()->inOrganization($this->org)->create();
    $schedule = Schedule::factory()->for($this->org)->published()->create();

    $user->notify(new SchedulePublishedNotification($schedule));
    $user->notify(new SchedulePublishedNotification($schedule));

    $this->actingAs($user)
        ->postJson('/notificacoes/lidas')
        ->assertOk();

    expect($user->unreadNotifications()->count())->toBe(0);
});

test('guests cannot access the notifications endpoints', function () {
    $this->getJson('/notificacoes')->assertUnauthorized();
});
