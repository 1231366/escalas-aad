<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\NotificationPrefsUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkProfileController extends Controller
{
    /**
     * Show the user's work profile settings page.
     */
    public function edit(Request $request): Response
    {
        $employee = $request->user()->employee;

        return Inertia::render('settings/work-profile', [
            'employee' => $employee ? [
                'name' => $employee->name,
                'regime' => $employee->regime->value,
                'regime_label' => $employee->regime->label(),
                'contract' => $employee->contract->value,
                'contract_label' => $employee->contract->label(),
                'weekly_hours' => $employee->contract->weeklyHours(),
                'fixa_noite' => $employee->fixa_noite,
                'active' => $employee->active,
            ] : null,
            'notification_prefs' => $request->user()->notification_prefs ?? [],
        ]);
    }

    /**
     * Update the user's notification preferences.
     */
    public function updateNotificationPrefs(NotificationPrefsUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();

        $prefs = $user->notification_prefs ?? [];
        $email = array_merge($prefs['email'] ?? [], $request->validated('email') ?? []);
        $prefs['email'] = $email;

        $user->notification_prefs = $prefs;
        $user->save();

        return to_route('work-profile.edit');
    }
}
