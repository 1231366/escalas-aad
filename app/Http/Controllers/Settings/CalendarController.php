<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    /**
     * Show the user's private iCal feed settings page.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();

        if ($user->calendar_token === null) {
            $user->regenerateCalendarToken();
        }

        return Inertia::render('settings/calendar', [
            'feed_url' => route('calendar.feed', $user->calendar_token),
        ]);
    }

    /**
     * Regenerate the user's calendar token, invalidating the previous feed URL.
     */
    public function regenerate(Request $request): RedirectResponse
    {
        $request->user()->regenerateCalendarToken();

        return to_route('calendar.edit');
    }
}
