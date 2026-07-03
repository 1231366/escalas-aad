<?php

namespace App\Http\Controllers;

use App\Enums\ScheduleStatus;
use App\Enums\SwapStatus;
use App\Enums\VacationStatus;
use App\Models\Employee;
use App\Models\Invitation;
use App\Models\Schedule;
use App\Models\ShiftType;
use App\Models\SwapRequest;
use App\Models\User;
use App\Models\VacationRequest;
use App\Services\ScheduleGridBuilder;
use App\Services\ViabilityCheck;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Painel inicial (PRD F10). Admin: estado do mês, pedidos pendentes,
 * viabilidade e equidade da última escala publicada. Funcionária: próximo
 * turno, semana atual e os seus próprios pedidos pendentes.
 */
class DashboardController extends Controller
{
    private const WEEKDAY_ABBR = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];

    public function index(Request $request, ViabilityCheck $viabilityCheck, ScheduleGridBuilder $grid): Response
    {
        $user = $request->user();
        $isAdmin = (bool) $user?->isAdmin();

        return Inertia::render('dashboard', [
            'viability' => $isAdmin ? $viabilityCheck->analyze() : null,
            'admin_stats' => $isAdmin ? $this->adminStats($grid) : null,
            'employee_stats' => $isAdmin ? null : $this->employeeStats($user, $grid),
            'shift_types' => $isAdmin ? [] : ShiftType::query()->orderedByShift()->get()
                ->map(fn (ShiftType $shiftType) => [
                    'id' => $shiftType->id,
                    'code' => $shiftType->code,
                    'name' => $shiftType->name,
                    'color' => $shiftType->color,
                ])->values(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function adminStats(ScheduleGridBuilder $grid): array
    {
        $today = Carbon::today()->toDateString();

        $currentSchedule = Schedule::query()
            ->where('period_start', '<=', $today)
            ->where('period_end', '>=', $today)
            ->first();

        return [
            'this_month' => [
                'schedule' => $currentSchedule ? [
                    'id' => $currentSchedule->id,
                    'status' => $currentSchedule->status->value,
                    'label' => ucfirst($currentSchedule->period_start->translatedFormat('F Y')),
                ] : null,
                'pending_swaps' => SwapRequest::query()->whereIn('status', [SwapStatus::Pending, SwapStatus::Accepted])->count(),
                'pending_vacations' => VacationRequest::query()->where('status', VacationStatus::Pending)->count(),
                'pending_invitations' => Invitation::query()
                    ->whereNull('accepted_at')
                    ->whereNull('revoked_at')
                    ->where('expires_at', '>', now())
                    ->count(),
            ],
            'equity' => $this->equity($grid),
        ];
    }

    /**
     * Equidade (S6/S8, ADR-0006) da última escala PUBLISHED: horas, fins de
     * semana, folgas e saldo de banco de horas por funcionária.
     *
     * @return array<string, mixed>|null
     */
    private function equity(ScheduleGridBuilder $grid): ?array
    {
        $schedule = Schedule::query()
            ->where('status', ScheduleStatus::Published)
            ->orderByDesc('period_start')
            ->first();

        if (! $schedule) {
            return null;
        }

        $employees = Employee::query()->active()->orderBy('name')->get();
        $dates = $grid->dates($schedule);
        $weeksInPeriod = $dates->count() > 0 ? $dates->count() / 7 : 0;

        $rows = $grid->employeeRows($schedule, $employees, $dates)->map(function (array $row) use ($employees, $weeksInPeriod) {
            $employee = $employees->firstWhere('id', $row['employee_id']);
            $contractualHours = round($weeksInPeriod * $employee->contract->weeklyHours(), 1);
            $balance = round($row['total_hours'] - $contractualHours, 1);

            return [
                'employee_id' => $row['employee_id'],
                'name' => $row['name'],
                'total_hours' => $row['total_hours'],
                'weekends_worked' => $row['weekends_worked'],
                'days_off' => $row['days_off'],
                'contractual_hours' => $contractualHours,
                'hour_bank_balance' => $balance,
                'hour_bank_label' => $this->formatBalance($balance),
            ];
        })->values();

        if ($rows->isEmpty()) {
            return [
                'schedule' => [
                    'id' => $schedule->id,
                    'label' => ucfirst($schedule->period_start->translatedFormat('F Y')),
                ],
                'employees' => [],
                'max_hours_employee_id' => null,
                'min_hours_employee_id' => null,
            ];
        }

        $maxHours = (float) $rows->max('total_hours');
        $minHours = (float) $rows->min('total_hours');

        return [
            'schedule' => [
                'id' => $schedule->id,
                'label' => ucfirst($schedule->period_start->translatedFormat('F Y')),
            ],
            'employees' => $rows->map(fn (array $row) => [
                ...$row,
                'bar_pct' => $maxHours > 0 ? (int) round(($row['total_hours'] / $maxHours) * 100) : 0,
            ])->values(),
            'max_hours_employee_id' => $rows->firstWhere('total_hours', $maxHours)['employee_id'] ?? null,
            'min_hours_employee_id' => $maxHours !== $minHours ? ($rows->firstWhere('total_hours', $minHours)['employee_id'] ?? null) : null,
        ];
    }

    private function formatBalance(float $balance): string
    {
        $abs = abs(round($balance, 1));
        $formatted = fmod($abs, 1.0) === 0.0 ? number_format($abs, 0) : number_format($abs, 1);

        return ($balance < 0 ? '-' : '+').$formatted.'h';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function employeeStats(?User $user, ScheduleGridBuilder $grid): ?array
    {
        $employee = $user?->employee;

        if (! $employee) {
            return null;
        }

        $pendingSwaps = SwapRequest::query()
            ->where(fn ($query) => $query
                ->where('requester_employee_id', $employee->id)
                ->orWhere('target_employee_id', $employee->id))
            ->whereIn('status', [SwapStatus::Pending, SwapStatus::Accepted])
            ->count();

        $pendingVacations = VacationRequest::query()
            ->where('employee_id', $employee->id)
            ->where('status', VacationStatus::Pending)
            ->count();

        $today = Carbon::today()->toDateString();

        $schedule = Schedule::query()
            ->where('status', ScheduleStatus::Published)
            ->where('period_start', '<=', $today)
            ->where('period_end', '>=', $today)
            ->first();

        if (! $schedule) {
            $schedule = Schedule::query()
                ->where('status', ScheduleStatus::Published)
                ->where('period_start', '>', $today)
                ->orderBy('period_start')
                ->first();
        }

        if (! $schedule) {
            return [
                'next_shift' => null,
                'current_week' => [],
                'pending_swaps' => $pendingSwaps,
                'pending_vacations' => $pendingVacations,
            ];
        }

        $dates = $grid->dates($schedule);
        /** @var array<string, mixed> $row */
        $row = $grid->employeeRows($schedule, collect([$employee]), $dates)->first();
        $cellsByDate = collect($row['cells'])->keyBy('date');

        $weekStart = Carbon::today()->startOfWeek()->toDateString();
        $weekEnd = Carbon::today()->endOfWeek()->toDateString();

        $nextShiftCell = $cellsByDate
            ->filter(fn (array $cell) => $cell['date'] >= $today && ! $cell['is_day_off'] && $cell['shift_code'] !== null)
            ->sortBy('date')
            ->first();

        return [
            'next_shift' => $nextShiftCell ? [
                'date' => $nextShiftCell['date'],
                'shift_code' => $nextShiftCell['shift_code'],
            ] : null,
            'current_week' => $dates
                ->filter(fn (CarbonInterface $date) => $date->toDateString() >= $weekStart && $date->toDateString() <= $weekEnd)
                ->map(function (CarbonInterface $date) use ($cellsByDate) {
                    $cell = $cellsByDate->get($date->toDateString());

                    return [
                        'date' => $date->toDateString(),
                        'day' => $date->day,
                        'weekday_label' => self::WEEKDAY_ABBR[$date->dayOfWeek],
                        'is_weekend' => $date->isWeekend(),
                        'is_today' => $date->isToday(),
                        'shift_code' => $cell['shift_code'] ?? null,
                        'is_day_off' => $cell['is_day_off'] ?? false,
                    ];
                })->values(),
            'pending_swaps' => $pendingSwaps,
            'pending_vacations' => $pendingVacations,
        ];
    }
}
