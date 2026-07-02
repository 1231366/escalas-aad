<?php

use App\Http\Controllers\Admin\InvitationController;
use App\Http\Controllers\Admin\RuleSettingsController;
use App\Http\Controllers\Admin\ScheduleExportController;
use App\Http\Controllers\CalendarFeedController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvitationAcceptanceController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

// Aceitação de convite — pública, o token é o segredo (PRD F2)
Route::middleware('guest')->group(function () {
    Route::get('convite/{token}', [InvitationAcceptanceController::class, 'show'])
        ->name('invitations.show');
    Route::post('convite/{token}', [InvitationAcceptanceController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('invitations.accept');
});

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
});

// Portal de administração
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('convites', [InvitationController::class, 'index'])->name('invitations.index');
    Route::post('convites', [InvitationController::class, 'store'])->name('invitations.store');
    Route::post('convites/{invitation}/reenviar', [InvitationController::class, 'resend'])->name('invitations.resend');
    Route::post('convites/{invitation}/revogar', [InvitationController::class, 'revoke'])->name('invitations.revoke');

    Route::get('regras', [RuleSettingsController::class, 'index'])->name('rules.index');
    Route::put('regras/cobertura', [RuleSettingsController::class, 'updateCoverage'])->name('rules.coverage.update');
    Route::put('regras/parametros', [RuleSettingsController::class, 'updateParameters'])->name('rules.parameters.update');
    Route::put('regras/turnos/{shiftType}', [RuleSettingsController::class, 'updateShiftType'])->name('rules.shift-types.update');

    Route::get('escalas/{schedule}/excel', [ScheduleExportController::class, 'download'])->name('schedules.export');
});

// Feed iCal privado por funcionária — público, o token é o segredo (PRD F9)
Route::get('calendario/{token}.ics', [CalendarFeedController::class, 'show'])
    ->middleware('throttle:30,1')
    ->name('calendar.feed');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
