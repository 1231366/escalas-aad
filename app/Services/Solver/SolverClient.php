<?php

namespace App\Services\Solver;

use App\Enums\ScheduleStatus;
use App\Models\Absence;
use App\Models\CoverageRule;
use App\Models\Employee;
use App\Models\RuleConfig;
use App\Models\Schedule;
use App\Models\ShiftAssignment;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Cliente HTTP do solver (ADR-0002). O Laravel nunca reimplementa regras de
 * escala: monta o payload a partir da BD e delega toda a lógica H1–H10/S1–S6
 * ao serviço Python/FastAPI. Stateless dos dois lados — cada pedido traz os
 * dados e a config da organização.
 *
 * As queries usam withoutGlobalScopes()+organization_id explícito (não
 * auth()->user()) porque este cliente é chamado a partir de um Job em fila
 * que pode correr sem utilizador autenticado (mesmo padrão do
 * CalendarFeedController para acesso público/background).
 */
class SolverClient
{
    private const SHIFT_HOURS = 8.0;

    private const INITIAL_STATE_DAYS = 7;

    private const MAX_ATTEMPTS = 5;

    public function generate(Schedule $schedule): array
    {
        return $this->post('/generate', $this->buildPayload($schedule));
    }

    /**
     * @param  list<array{employee_id:int,date:string,shift:?string}>  $assignments
     */
    public function validate(Schedule $schedule, array $assignments): array
    {
        $payload = $this->buildPayload($schedule);
        $payload['assignments'] = $assignments;

        return $this->post('/validate', $payload);
    }

    /**
     * Testa o impacto de um pedido de férias na cobertura (PRD F6). Só faz
     * sentido pedir ao solver se já houver uma escala PUBLISHED cobrindo
     * (parte de) o intervalo — sem isso não há nada para "partir" ainda, e
     * evitamos uma chamada HTTP desnecessária.
     */
    public function vacationImpact(Employee $employee, string $start, string $end): array
    {
        $schedule = Schedule::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $employee->organization_id)
            ->where('status', ScheduleStatus::Published)
            ->where('period_start', '<=', $end)
            ->where('period_end', '>=', $start)
            ->orderBy('period_start')
            ->first();

        if (! $schedule) {
            return ['ok' => true, 'issues' => [], 'no_schedule' => true];
        }

        $payload = $this->buildPayload($schedule);
        $payload['assignments'] = ShiftAssignment::query()
            ->where('schedule_id', $schedule->id)
            ->with(['shiftType' => fn ($query) => $query->withoutGlobalScopes()])
            ->get()
            ->map(fn (ShiftAssignment $assignment) => [
                'employee_id' => $assignment->employee_id,
                'date' => $assignment->date->toDateString(),
                'shift' => $assignment->shiftType?->code,
            ])
            ->values()
            ->all();
        $payload['employee_id'] = $employee->id;
        $payload['start'] = $start;
        $payload['end'] = $end;

        return $this->post('/vacation-impact', $payload);
    }

    /**
     * Candidatas a troca (PRD F5): colegas com quem $requester pode trocar o
     * turno de $date sem violar nenhuma hard. Resposta: {candidates:
     * [{employee_id, shift}]} — "shift" é o turno que $requester passaria a
     * ter (o turno atual da colega nesse dia).
     */
    public function swapCandidates(Schedule $schedule, Employee $requester, string $date): array
    {
        $payload = $this->buildPayload($schedule);
        $payload['assignments'] = $this->currentAssignments($schedule);
        $payload['requester_employee_id'] = $requester->id;
        $payload['date'] = $date;

        return $this->post('/swap-candidates', $payload);
    }

    /**
     * Estado atual completo da escala no formato "assignments" do solver.
     * Reutilizado por swapCandidates() e por quem precisa de revalidar uma
     * troca (SwapController) sem duplicar a leitura da BD.
     *
     * @return list<array{employee_id:int,date:string,shift:?string}>
     */
    public function currentAssignments(Schedule $schedule): array
    {
        return $schedule->assignments()
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

    /**
     * O solver corre no plano gratuito do Render, que o adormece ao fim de
     * ~15 min sem pedidos — o próximo pedido acorda-o mas o Render devolve
     * 502/503/504 durante os segundos em que o container ainda está a
     * arrancar. Em vez de rebentar logo com esse erro transitório, tentamos
     * de novo com pausas crescentes até o solver acordar de vez.
     */
    private function post(string $path, array $payload): array
    {
        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            try {
                $response = Http::baseUrl(config('services.solver.url'))
                    ->timeout(90)
                    ->acceptJson()
                    ->post($path, $payload);
            } catch (Throwable $e) {
                if ($attempt === self::MAX_ATTEMPTS) {
                    throw new SolverUnavailableException("Solver indisponível ({$path}): {$e->getMessage()}", previous: $e);
                }

                $this->sleepBeforeRetry($attempt);

                continue;
            }

            if (in_array($response->status(), [502, 503, 504], true) && $attempt < self::MAX_ATTEMPTS) {
                $this->sleepBeforeRetry($attempt);

                continue;
            }

            if ($response->failed()) {
                throw new SolverUnavailableException("Solver devolveu erro {$response->status()} em {$path}: {$response->body()}");
            }

            return $response->json() ?? [];
        }

        throw new SolverUnavailableException("Solver indisponível ({$path}) após ".self::MAX_ATTEMPTS.' tentativas.');
    }

    private function sleepBeforeRetry(int $attempt): void
    {
        usleep(min($attempt * 3_000_000, 15_000_000));
    }

    /**
     * Monta o GenerateRequest do solver/app/schemas.py a partir da BD.
     */
    private function buildPayload(Schedule $schedule): array
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
            'period_start' => $schedule->period_start->toDateString(),
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
            'absences' => $this->buildAbsences($schedule, $organizationId),
            'initial_state' => $this->buildInitialState($schedule, $organizationId),
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

    private function buildAbsences(Schedule $schedule, int $organizationId): array
    {
        return Absence::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('start_date', '<=', $schedule->period_end)
            ->where('end_date', '>=', $schedule->period_start)
            ->get()
            ->map(fn (Absence $absence) => [
                'employee_id' => $absence->employee_id,
                'start' => $absence->start_date->toDateString(),
                'end' => $absence->end_date->toDateString(),
            ])
            ->values()
            ->all();
    }

    /**
     * Últimos dias do schedule PUBLISHED imediatamente anterior, para o
     * solver conseguir aplicar H3/H5/H9 na fronteira entre meses.
     */
    private function buildInitialState(Schedule $schedule, int $organizationId): array
    {
        $previous = Schedule::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('status', ScheduleStatus::Published)
            ->where('period_end', '<', $schedule->period_start)
            ->orderByDesc('period_end')
            ->first();

        if (! $previous) {
            return [];
        }

        $cutoff = $previous->period_end->copy()->subDays(self::INITIAL_STATE_DAYS - 1);

        return ShiftAssignment::query()
            ->where('schedule_id', $previous->id)
            ->where('date', '>=', $cutoff->toDateString())
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
