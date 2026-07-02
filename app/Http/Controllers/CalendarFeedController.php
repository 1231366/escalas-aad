<?php

namespace App\Http\Controllers;

use App\Enums\ScheduleStatus;
use App\Models\Employee;
use App\Models\Schedule;
use App\Models\ShiftAssignment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Response;
use Spatie\IcalendarGenerator\Components\Calendar;
use Spatie\IcalendarGenerator\Components\Event;

/**
 * Feed iCal privado por funcionária (PRD F9). Público — o token é o segredo,
 * não há utilizador autenticado, por isso os scopes de organização têm de
 * ser desligados explicitamente em cada query.
 */
class CalendarFeedController extends Controller
{
    public function show(string $token): Response
    {
        $user = User::withoutGlobalScopes()->where('calendar_token', $token)->first();

        abort_if($user === null, 404);

        $employee = Employee::withoutGlobalScopes()->where('user_id', $user->id)->first();

        abort_if($employee === null, 404);

        $publishedScheduleIds = Schedule::withoutGlobalScopes()
            ->where('organization_id', $employee->organization_id)
            ->where('status', ScheduleStatus::Published)
            ->pluck('id');

        $assignments = ShiftAssignment::query()
            ->where('employee_id', $employee->id)
            ->whereIn('schedule_id', $publishedScheduleIds)
            ->whereNotNull('shift_type_id')
            ->where('date', '>=', now('Europe/Lisbon')->subMonths(2)->startOfDay()->toDateString())
            ->with(['shiftType' => fn ($query) => $query->withoutGlobalScopes()])
            ->orderBy('date')
            ->get();

        $organizationName = $employee->organization()->withoutGlobalScopes()->value('name') ?? 'Escalas';

        $calendar = Calendar::create("Escalas — {$organizationName}")
            ->refreshInterval(60)
            ->productIdentifier('-//Escalas AAD//Feed iCal//PT');

        foreach ($assignments as $assignment) {
            $calendar->event($this->toEvent($assignment));
        }

        return response($calendar->get(), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
        ]);
    }

    private function toEvent(ShiftAssignment $assignment): Event
    {
        $shiftType = $assignment->shiftType;
        $dateString = $assignment->date->toDateString();

        $startsAt = Carbon::parse("{$dateString} {$shiftType->starts_at}", 'Europe/Lisbon');
        $endsAt = Carbon::parse("{$dateString} {$shiftType->ends_at}", 'Europe/Lisbon');

        // Turnos que atravessam a meia-noite (ex.: T 16:00–00:00) terminam no dia seguinte.
        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            $endsAt->addDay();
        }

        return Event::create("Turno {$shiftType->name}")
            ->uniqueIdentifier("escalas-{$assignment->id}@escalas-aad")
            ->startsAt($startsAt)
            ->endsAt($endsAt)
            ->alertMinutesBefore(60);
    }
}
