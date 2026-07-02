<?php

namespace Database\Seeders;

use App\Enums\ContractType;
use App\Enums\Regime;
use App\Enums\Role;
use App\Models\CoverageRule;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\RuleConfig;
use App\Models\ShiftType;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Cenário da folha manuscrita: 12 AAD, 2 fixas de noite, cobertura 4M/3T/2N.
 * Login demo: admin@demo.test / password
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $org = Organization::create([
            'name' => 'Lar Demo',
            'settings' => ['swap_requires_admin_approval' => true],
        ]);

        $admin = User::factory()->admin()->inOrganization($org)->create([
            'name' => 'Diretora Técnica',
            'email' => 'admin@demo.test',
        ]);
        $admin->regenerateCalendarToken();

        // Turnos e cobertura da folha (4M/3T/2N, todos os dias da semana)
        $shifts = [
            'M' => ShiftType::factory()->for($org)->create(),
            'T' => ShiftType::factory()->tarde()->for($org)->create(),
            'N' => ShiftType::factory()->noite()->for($org)->create(),
        ];

        $coverage = ['M' => 4, 'T' => 3, 'N' => 2];
        foreach ($shifts as $code => $shift) {
            foreach (range(0, 6) as $weekday) {
                CoverageRule::create([
                    'organization_id' => $org->id,
                    'shift_type_id' => $shift->id,
                    'weekday' => $weekday,
                    'required' => $coverage[$code],
                ]);
            }
        }

        foreach (RuleConfig::defaults() as $key => $value) {
            RuleConfig::create([
                'organization_id' => $org->id,
                'key' => $key,
                'value' => $value,
            ]);
        }

        // 12 funcionárias: 2 fixas noite + 2 híbridas (pool de noite ≥4, ADR-0004/Q2)
        // + 8 só dia. Contratos mistos como na folha.
        $team = [
            ['Alice Fontes', Regime::Noite, true, ContractType::H40],
            ['Beatriz Ramos', Regime::Noite, true, ContractType::H40],
            ['Carla Nunes', Regime::Hibrido, false, ContractType::H40],
            ['Diana Costa', Regime::Hibrido, false, ContractType::H37_30],
            ['Elsa Martins', Regime::Dia, false, ContractType::H40],
            ['Filipa Sousa', Regime::Dia, false, ContractType::H40],
            ['Gabriela Pinto', Regime::Dia, false, ContractType::H37_30],
            ['Helena Silva', Regime::Dia, false, ContractType::H40],
            ['Inês Ferreira', Regime::Dia, false, ContractType::H40],
            ['Joana Lopes', Regime::Dia, false, ContractType::H37_30],
            ['Luísa Baptista', Regime::Dia, false, ContractType::H40],
            ['Marta Rocha', Regime::Dia, false, ContractType::H40],
        ];

        foreach ($team as $index => [$name, $regime, $fixaNoite, $contract]) {
            $email = 'aad'.($index + 1).'@demo.test';

            $user = User::factory()->inOrganization($org)->create([
                'name' => $name,
                'email' => $email,
                'role' => Role::Employee,
            ]);
            $user->regenerateCalendarToken();

            Employee::create([
                'organization_id' => $org->id,
                'user_id' => $user->id,
                'name' => $name,
                'regime' => $regime,
                'contract' => $contract,
                'fixa_noite' => $fixaNoite,
                'active' => true,
            ]);
        }
    }
}
