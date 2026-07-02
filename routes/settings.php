<?php

use App\Http\Controllers\Settings\CalendarController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\WorkProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('settings/password', [PasswordController::class, 'update'])->name('password.update');

    Route::get('settings/trabalho', [WorkProfileController::class, 'edit'])->name('work-profile.edit');
    Route::patch('settings/notificacoes', [WorkProfileController::class, 'updateNotificationPrefs'])->name('notification-prefs.update');

    Route::get('settings/calendario', [CalendarController::class, 'edit'])->name('calendar.edit');
    Route::post('settings/calendario/regenerar', [CalendarController::class, 'regenerate'])->name('calendar.regenerate');

    Route::get('settings/appearance', function () {
        return Inertia::render('settings/appearance');
    })->name('appearance');
});
