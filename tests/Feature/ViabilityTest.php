<?php

use App\Enums\ContractType;
use App\Enums\Regime;
use App\Models\CoverageRule;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\RuleConfig;
use App\Models\ShiftType;
use App\Models\User;
use App\Services\ViabilityCheck;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Cria os turnos M/T/N e a cobertura da folha (4M/3T/2N todos os dias, salvo overrides)
 * para a organização autenticada.
 *
 * @param  array<int, array{0: int, 1: int, 2: int}>  $overrides  weekday => [M, T, N], substitui o default nesse dia.
 */
function seedCoverage(Organization $org, array $overrides = []): void
{
    $shifts = [
        'M' => ShiftType::factory()->for($org)->create(),
        'T' => ShiftType::factory()->tarde()->for($org)->create(),
        'N' => ShiftType::factory()->noite()->for($org)->create(),
    ];

    foreach (range(0, 6) as $weekday) {
        [$m, $t, $n] = $overrides[$weekday] ?? [4, 3, 2];

        CoverageRule::create(['organization_id' => $org->id, 'shift_type_id' => $shifts['M']->id, 'weekday' => $weekday, 'required' => $m]);
        CoverageRule::create(['organization_id' => $org->id, 'shift_type_id' => $shifts['T']->id, 'weekday' => $weekday, 'required' => $t]);
        CoverageRule::create(['organization_id' => $org->id, 'shift_type_id' => $shifts['N']->id, 'weekday' => $weekday, 'required' => $n]);
    }
}

/**
 * Recria a equipa de 12 AAD da folha manuscrita (DemoSeeder): 2 NOITE fixas,
 * 2 HIBRIDO, 8 DIA; contratos 3×37h30 + 9×40h.
 *
 * @param  array<int, Regime>  $regimeOverrides  índice (0-based, ordem da folha) => regime a forçar.
 */
function seedTeam(Organization $org, array $regimeOverrides = []): void
{
    $team = [
        [Regime::Noite, true, ContractType::H40],
        [Regime::Noite, true, ContractType::H40],
        [Regime::Hibrido, false, ContractType::H40],
        [Regime::Hibrido, false, ContractType::H37_30],
        [Regime::Dia, false, ContractType::H40],
        [Regime::Dia, false, ContractType::H40],
        [Regime::Dia, false, ContractType::H37_30],
        [Regime::Dia, false, ContractType::H40],
        [Regime::Dia, false, ContractType::H40],
        [Regime::Dia, false, ContractType::H37_30],
        [Regime::Dia, false, ContractType::H40],
        [Regime::Dia, false, ContractType::H40],
    ];

    foreach ($team as $index => [$regime, $fixaNoite, $contract]) {
        Employee::factory()->for($org)->create([
            'regime' => $regimeOverrides[$index] ?? $regime,
            'fixa_noite' => $fixaNoite,
            'contract' => $contract,
            'active' => true,
        ]);
    }
}

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->admin = User::factory()->admin()->inOrganization($this->org)->create();
    $this->actingAs($this->admin);

    foreach (RuleConfig::defaults() as $key => $value) {
        RuleConfig::create(['organization_id' => $this->org->id, 'key' => $key, 'value' => $value]);
    }
});

test('cenário da folha (12 AAD, cobertura 4/3/2) é tight: défice contratual, banco de horas fecha', function () {
    seedCoverage($this->org);
    seedTeam($this->org);

    $result = (new ViabilityCheck)->analyze();

    // Procura: (4+3+2) turnos/dia × 7 dias = 63 turnos/semana = 504h.
    expect($result['demand']['shifts_per_week'])->toBe(63)
        ->and($result['demand']['hours_per_week'])->toBe(504.0);

    // Oferta contratual: 3×37h30 + 9×40h = 472,5h -> 59,1 turnos/semana (não 60 "redondo":
    // ver nota de arredondamento no ViabilityCheck — usamos horas como métrica de verdade).
    expect($result['supply']['contractual']['hours_per_week'])->toBe(472.5)
        ->and($result['supply']['contractual']['shifts_per_week'])->toBe(59.1);

    // Défice contratual em horas: 504 - 472,5 = 31,5h (~3,9 turnos/semana),
    // na mesma ordem de grandeza do -3 turnos de docs/01-planeamento.md §2
    // (que assume os 12 contratos a 40h, sem os 3×37h30 reais da folha).
    expect($result['balance']['contractual']['hours_per_week'])->toBe(-31.5)
        ->and($result['balance']['contractual']['shifts_per_week'])->toBe(-3.9);

    // Com banco de horas (+4h/semana × 12 = +48h): 472,5 + 48 = 520,5h >= 504h -> fecha.
    expect($result['supply']['with_hour_bank']['hours_per_week'])->toBe(520.5)
        ->and($result['balance']['with_hour_bank']['hours_per_week'])->toBe(16.5);

    expect($result['status'])->toBe('tight');

    // Pool de noite: 2 fixas + 2 híbridas = 4 -> suficiente.
    expect($result['night']['pool_size'])->toBe(4)
        ->and($result['night']['pool_ok'])->toBeTrue()
        ->and($result['night']['required_shifts_per_week'])->toBe(14);

    expect($result['suggestions'])->toContain('Reduzir a cobertura ao fim de semana em Regras (/admin/regras), ex.: 3M/2T/2N ao sábado e domingo.');
});

test('reduzir a cobertura ao fim de semana melhora o estado para ok', function () {
    // Sáb (5) e Dom (6) passam de 4/3/2 para 3/2/2.
    seedCoverage($this->org, [5 => [3, 2, 2], 6 => [3, 2, 2]]);
    seedTeam($this->org);

    $result = (new ViabilityCheck)->analyze();

    // Procura: 5 dias × 9 + 2 dias × 7 = 45 + 14 = 59 turnos/semana = 472h.
    expect($result['demand']['shifts_per_week'])->toBe(59)
        ->and($result['demand']['hours_per_week'])->toBe(472.0)
        ->and($result['balance']['contractual']['hours_per_week'])->toBe(0.5)
        ->and($result['status'])->toBe('ok');
});

test('com apenas 3 elegíveis à noite o pool fica insuficiente', function () {
    seedCoverage($this->org);
    // Força a 4ª pessoa (índice 3, Hibrido por omissão) para DIA -> só sobram 3 não-DIA.
    seedTeam($this->org, [3 => Regime::Dia]);

    $result = (new ViabilityCheck)->analyze();

    expect($result['night']['pool_size'])->toBe(3)
        ->and($result['night']['pool_ok'])->toBeFalse()
        ->and($result['suggestions'])->toContain('Adicionar mais funcionárias elegíveis à noite (regime NOITE ou HÍBRIDO) para reforçar o pool noturno.');
});

test('funcionária vê o dashboard sem viability; admin vê com viability', function () {
    seedCoverage($this->org);
    seedTeam($this->org);

    $employeeUser = User::factory()->inOrganization($this->org)->create();

    $this->actingAs($employeeUser)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('viability', null)
        );

    $this->actingAs($this->admin)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('viability.status', 'tight')
            ->has('viability.suggestions')
        );
});
