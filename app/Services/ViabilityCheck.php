<?php

namespace App\Services;

use App\Enums\Regime;
use App\Models\CoverageRule;
use App\Models\Employee;
use App\Models\RuleConfig;
use App\Models\ShiftType;

/**
 * Check de viabilidade da escala (ADR-0003, docs/01-planeamento.md §2).
 *
 * Compara a procura de turnos/semana (cobertura configurada) com a oferta
 * de turnos/semana (carga contratual das funcionárias ativas), com e sem
 * banco de horas, e avalia se o pool de noite tem dimensão suficiente.
 *
 * Nota de arredondamento: 1 turno = 8h (pressuposto Q4 do planeamento).
 * Contratos de 37h30 correspondem a 4,6875 turnos, uma fração sem
 * significado operacional (não existem "meios turnos"). Para não fingir
 * precisão inexistente nem esconder o défice, as HORAS são a métrica de
 * verdade para o cálculo do saldo/estado (comparação exata); os TURNOS
 * são derivados de horas ÷ 8 apenas para leitura mais intuitiva no
 * dashboard, arredondados a 1 casa decimal. Por isso, com o cenário da
 * folha (3×37h30 + 9×40h), a oferta contratual sai ~59,1 turnos/semana
 * (não 60 "redondo") — o défice em horas (31,5h) é o número que decide
 * o estado.
 */
class ViabilityCheck
{
    /** Nº mínimo de pessoas no pool de noite para cobrir 2N/dia em ciclo NNNFF (CONTEXT.md). */
    private const MIN_NIGHT_POOL = 4;

    private const HOURS_PER_SHIFT = 8.0;

    public function analyze(): array
    {
        $activeEmployees = Employee::query()->active()->get();
        $employeesCount = $activeEmployees->count();

        $tolerance = (float) RuleConfig::get('hour_bank_weekly_tolerance', RuleConfig::defaults()['hour_bank_weekly_tolerance']);

        // Procura: soma de todas as células de cobertura (turnos × 7 dias).
        $demandShifts = (int) CoverageRule::query()->sum('required');
        $demandHours = $demandShifts * self::HOURS_PER_SHIFT;

        // Oferta contratual: soma da carga semanal contratual das funcionárias ativas.
        $supplyContractualHours = $activeEmployees->sum(fn (Employee $employee) => $employee->contract->weeklyHours());
        $supplyContractualShifts = $supplyContractualHours / self::HOURS_PER_SHIFT;

        // Oferta com banco de horas: + tolerância × nº de funcionárias.
        $supplyWithBankHours = $supplyContractualHours + ($tolerance * $employeesCount);
        $supplyWithBankShifts = $supplyWithBankHours / self::HOURS_PER_SHIFT;

        $balanceContractualHours = $supplyContractualHours - $demandHours;
        $balanceWithBankHours = $supplyWithBankHours - $demandHours;

        $status = match (true) {
            $balanceContractualHours >= 0 => 'ok',
            $balanceWithBankHours >= 0 => 'tight',
            default => 'deficit',
        };

        // Noite: pool = funcionárias ativas com regime != DIA (fixas contam).
        $nightPoolSize = $activeEmployees->filter(fn (Employee $employee) => $employee->regime !== Regime::Dia)->count();
        $nightPoolOk = $nightPoolSize >= self::MIN_NIGHT_POOL;

        $nightShiftIds = ShiftType::query()->where('code', 'N')->pluck('id');
        $nightRequiredPerWeek = (int) CoverageRule::query()->whereIn('shift_type_id', $nightShiftIds)->sum('required');

        return [
            'status' => $status,
            'employees_count' => $employeesCount,
            'hour_bank_weekly_tolerance' => $tolerance,
            'demand' => [
                'shifts_per_week' => $demandShifts,
                'hours_per_week' => $demandHours,
            ],
            'supply' => [
                'contractual' => [
                    'shifts_per_week' => round($supplyContractualShifts, 1),
                    'hours_per_week' => round($supplyContractualHours, 1),
                ],
                'with_hour_bank' => [
                    'shifts_per_week' => round($supplyWithBankShifts, 1),
                    'hours_per_week' => round($supplyWithBankHours, 1),
                ],
            ],
            // Saldo = oferta - procura. Positivo = folga, negativo = défice.
            'balance' => [
                'contractual' => [
                    'shifts_per_week' => round($balanceContractualHours / self::HOURS_PER_SHIFT, 1),
                    'hours_per_week' => round($balanceContractualHours, 1),
                ],
                'with_hour_bank' => [
                    'shifts_per_week' => round($balanceWithBankHours / self::HOURS_PER_SHIFT, 1),
                    'hours_per_week' => round($balanceWithBankHours, 1),
                ],
            ],
            'night' => [
                'required_shifts_per_week' => $nightRequiredPerWeek,
                'pool_size' => $nightPoolSize,
                'pool_ok' => $nightPoolOk,
                'min_pool_size' => self::MIN_NIGHT_POOL,
            ],
            'suggestions' => $this->suggestions($status, $nightPoolOk),
        ];
    }

    /**
     * @return list<string>
     */
    private function suggestions(string $status, bool $nightPoolOk): array
    {
        $suggestions = [];

        if ($status !== 'ok') {
            $suggestions[] = 'Reduzir a cobertura ao fim de semana em Regras (/admin/regras), ex.: 3M/2T/2N ao sábado e domingo.';
        }

        if ($status === 'tight') {
            $suggestions[] = 'Aumentar a tolerância do banco de horas em Regras (/admin/regras) se a carga adicional for sustentável.';
        }

        if ($status === 'deficit') {
            $suggestions[] = 'Aumentar a tolerância do banco de horas em Regras (/admin/regras).';
            $suggestions[] = 'Convidar mais uma funcionária para reforçar a equipa.';
        }

        if (! $nightPoolOk) {
            $suggestions[] = 'Adicionar mais funcionárias elegíveis à noite (regime NOITE ou HÍBRIDO) para reforçar o pool noturno.';
        }

        return $suggestions;
    }
}
