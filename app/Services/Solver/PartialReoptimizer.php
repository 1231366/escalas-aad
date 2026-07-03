<?php

namespace App\Services\Solver;

use App\Enums\VacationStatus;
use App\Models\Absence;
use App\Models\CoverageRule;
use App\Models\Employee;
use App\Models\RuleConfig;
use App\Models\Schedule;
use App\Models\ShiftAssignment;
use App\Models\VacationRequest;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Re-otimização parcial de uma escala PUBLISHED a partir de uma data de
 * corte (issue #18, PRD F6): pede ao solver um novo POST /generate só para o
 * troço [corte, period_end], preservando os dias já passados.
 *
 * Deliberadamente não reutiliza SolverClient (que assume sempre o período
 * completo de um Schedule DRAFT e o initial_state da escala PUBLISHED
 * *anterior*): aqui o "initial_state" são os 7 dias reais imediatamente
 * antes do corte, dentro da MESMA escala, e o período pedido é só o troço
 * futuro. O payload (employees/coverage/config) é montado da mesma forma
 * que em SolverClient::buildPayload — ver esse ficheiro para o formato
 * esperado pelo solver/app/schemas.py.
 */
class PartialReoptimizer
{
    private const SHIFT_HOURS = 8.0;

    private const INITIAL_STATE_DAYS = 7;

    public function reoptimize(Schedule $schedule, CarbonInterface $cutoff): array
    {
        $payload = $this->buildPayload($schedule, $cutoff);

        try {
            $response = Http::baseUrl(config('services.solver.url'))
                ->timeout(90)
                ->acceptJson()
                ->post('/generate', $payload);
        } catch (Throwable $e) {
            throw new SolverUnavailableException("Solver indisponível (/generate): {$e->getMessage()}", previous: $e);
        }

        if ($response->failed()) {
            throw new SolverUnavailableException("Solver devolveu erro {$response->status()} em /generate: {$response->body()}");
        }

        return $response->json() ?? [];
    }

    private function buildPayload(Schedule $schedule, CarbonInterface $cutoff): array
    {
        $organizationId = $schedule->organization_id;

        $employees = Employee::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('active', true)
            ->orderBy('id')
            ->get();

        $coverage = CoverageRule::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->with(['shiftType' => fn ($query) => $query->withoutGlobalScopes()])
            ->get()
            ->map(fn (CoverageRule $rule) => [
                'weekday' => $rule->weekday,
                'shift' => $rule->shiftType->code,
                'required' => $rule->required,
            ])
            ->values()
            ->all();

        return [
            'period_start' => $cutoff->toDateString(),
            'period_end' => $schedule->period_end->toDateString(),
            'employees' => $employees->map(fn (Employee $employee) => [
                'id' => $employee->id,
                'name' => $employee->name,
                'contract_hours' => $employee->contract->weeklyHours(),
                'regime' => $employee->regime->value,
                'fixa_noite' => $employee->fixa_noite,
            ])->values()->all(),
            'coverage' => $coverage,
            'config' => $this->buildConfig($organizationId),
            'absences' => $this->buildAbsences($organizationId, $cutoff, $schedule->period_end),
            'initial_state' => $this->buildInitialState($schedule, $cutoff),
        ];
    }

    private function buildConfig(int $organizationId): array
    {
        $defaults = RuleConfig::defaults();

        $stored = RuleConfig::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->get()
            ->pluck('value', 'key');

        return [
            'hour_bank_weekly_tolerance' => (float) ($stored['hour_bank_weekly_tolerance'] ?? $defaults['hour_bank_weekly_tolerance']),
            'max_consecutive_work_days' => (int) ($stored['max_consecutive_work_days'] ?? $defaults['max_consecutive_work_days']),
            'ff_window_weeks' => (int) ($stored['ff_window_weeks'] ?? $defaults['ff_window_weeks']),
            'ff_monthly' => (bool) ($stored['ff_monthly'] ?? $defaults['ff_monthly']),
            'shift_hours' => self::SHIFT_HOURS,
            'forbidden_transitions' => $stored['forbidden_transitions'] ?? $defaults['forbidden_transitions'],
        ];
    }

    /**
     * Ausências ativas no troço re-otimizado + férias já APPROVED (para o
     * solver as preservar como folga — a mesma razão pela qual
     * VacationController::approve marca essas células com origin VACATION
     * em vez de as apagar).
     */
    private function buildAbsences(int $organizationId, CarbonInterface $periodStart, CarbonInterface $periodEnd): array
    {
        $absences = Absence::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('start_date', '<=', $periodEnd)
            ->where('end_date', '>=', $periodStart)
            ->get()
            ->map(fn (Absence $absence) => [
                'employee_id' => $absence->employee_id,
                'start' => $absence->start_date->toDateString(),
                'end' => $absence->end_date->toDateString(),
            ]);

        $vacations = VacationRequest::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('status', VacationStatus::Approved)
            ->where('start_date', '<=', $periodEnd)
            ->where('end_date', '>=', $periodStart)
            ->get()
            ->map(fn (VacationRequest $vacation) => [
                'employee_id' => $vacation->employee_id,
                'start' => $vacation->start_date->toDateString(),
                'end' => $vacation->end_date->toDateString(),
            ]);

        return $absences->concat($vacations)->values()->all();
    }

    /**
     * Os 7 dias reais imediatamente antes do corte, dentro da própria
     * escala (não a escala anterior — H3/H9 na fronteira do troço
     * re-otimizado dependem do que a pessoa fez mesmo antes do corte).
     */
    private function buildInitialState(Schedule $schedule, CarbonInterface $cutoff): array
    {
        $from = $cutoff->copy()->subDays(self::INITIAL_STATE_DAYS);

        return ShiftAssignment::query()
            ->where('schedule_id', $schedule->id)
            ->where('date', '>=', $from->toDateString())
            ->where('date', '<', $cutoff->toDateString())
            ->with(['shiftType' => fn ($query) => $query->withoutGlobalScopes()])
            ->get()
            ->map(fn (ShiftAssignment $assignment) => [
                'employee_id' => $assignment->employee_id,
                'date' => $assignment->date->toDateString(),
                'shift' => $assignment->shiftType?->code,
            ])
            ->values()
            ->all();
    }
}
