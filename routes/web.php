<?php

use App\Http\Controllers\Admin\AbsenceController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\InvitationController;
use App\Http\Controllers\Admin\RuleSettingsController;
use App\Http\Controllers\Admin\ScheduleController as AdminScheduleController;
use App\Http\Controllers\Admin\ScheduleExportController;
use App\Http\Controllers\Admin\VacationController as AdminVacationController;
use App\Http\Controllers\CalendarFeedController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvitationAcceptanceController;
use App\Http\Controllers\MyScheduleController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\VacationController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
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
    // Consulta da escala pela funcionária — PUBLISHED do mês corrente ou próxima (PRD F4).
    Route::get('escala', [MyScheduleController::class, 'show'])->name('my-schedule');

    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // API in-app para o sino de notificações — polling 30s, sem websockets (ADR-0005 / PRD F7)
    Route::get('notificacoes', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notificacoes/{id}/lida', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('notificacoes/lidas', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');

    // Pedidos de férias da funcionária — impacto na cobertura calculado pelo solver (PRD F6).
    Route::get('ferias', [VacationController::class, 'index'])->name('vacations.index');
    Route::post('ferias', [VacationController::class, 'store'])->name('vacations.store');
    Route::post('ferias/{vacationRequest}/cancelar', [VacationController::class, 'cancel'])->name('vacations.cancel');
});

// Portal de administração
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    // Funcionárias sem conta de acesso — mockar a escala sem convidar ninguém (PRD).
    Route::get('funcionarios', [EmployeeController::class, 'index'])->name('employees.index');
    Route::post('funcionarios', [EmployeeController::class, 'store'])->name('employees.store');
    Route::put('funcionarios/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
    Route::delete('funcionarios/{employee}', [EmployeeController::class, 'destroy'])->name('employees.destroy');

    Route::get('convites', [InvitationController::class, 'index'])->name('invitations.index');
    Route::post('convites', [InvitationController::class, 'store'])->name('invitations.store');
    Route::post('convites/{invitation}/reenviar', [InvitationController::class, 'resend'])->name('invitations.resend');
    Route::post('convites/{invitation}/revogar', [InvitationController::class, 'revoke'])->name('invitations.revoke');

    Route::get('regras', [RuleSettingsController::class, 'index'])->name('rules.index');
    Route::put('regras/cobertura', [RuleSettingsController::class, 'updateCoverage'])->name('rules.coverage.update');
    Route::put('regras/parametros', [RuleSettingsController::class, 'updateParameters'])->name('rules.parameters.update');
    Route::put('regras/turnos/{shiftType}', [RuleSettingsController::class, 'updateShiftType'])->name('rules.shift-types.update');

    Route::get('escalas/{schedule}/excel', [ScheduleExportController::class, 'download'])->name('schedules.export');

    // Geração/publicação da escala mensal via solver (PRD F4, ADR-0002).
    Route::get('escalas', [AdminScheduleController::class, 'index'])->name('schedules.index');
    Route::post('escalas', [AdminScheduleController::class, 'store'])->name('schedules.store');
    Route::get('escalas/{schedule}', [AdminScheduleController::class, 'show'])->name('schedules.show');
    Route::post('escalas/{schedule}/gerar', [AdminScheduleController::class, 'regenerate'])->name('schedules.regenerate');
    Route::post('escalas/{schedule}/publicar', [AdminScheduleController::class, 'publish'])->name('schedules.publish');
    Route::post('escalas/{schedule}/arquivar', [AdminScheduleController::class, 'archive'])->name('schedules.archive');
    // Edição manual de uma célula da grelha com revalidação síncrona pelo solver (issue #12, ADR-0002).
    Route::patch('escalas/{schedule}/celula', [AdminScheduleController::class, 'updateCell'])->name('schedules.cell.update');

    // Auditoria — histórico de mutações da org (PRD F10).
    Route::get('auditoria', [AuditLogController::class, 'index'])->name('audit.index');

    // Decisão de pedidos de férias com o impacto na cobertura à vista (PRD F6).
    Route::get('ferias', [AdminVacationController::class, 'index'])->name('vacations.index');
    Route::post('ferias/{vacationRequest}/aprovar', [AdminVacationController::class, 'approve'])->name('vacations.approve');
    Route::post('ferias/{vacationRequest}/recusar', [AdminVacationController::class, 'decline'])->name('vacations.decline');

    // Ausências (baixa/falta): aviso de buracos de cobertura + re-otimização parcial (issue #18, PRD F6).
    Route::get('ausencias', [AbsenceController::class, 'index'])->name('absences.index');
    Route::post('ausencias', [AbsenceController::class, 'store'])->name('absences.store');
    Route::delete('ausencias/{absence}', [AbsenceController::class, 'destroy'])->name('absences.destroy');
    Route::post('ausencias/{absence}/reotimizar', [AbsenceController::class, 'reoptimize'])->name('absences.reoptimize');
});

// Feed iCal privado por funcionária — público, o token é o segredo (PRD F9)
Route::get('calendario/{token}.ics', [CalendarFeedController::class, 'show'])
    ->middleware('throttle:30,1')
    ->name('calendar.feed');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
