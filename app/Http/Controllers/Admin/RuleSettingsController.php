<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateCoverageRequest;
use App\Http\Requests\UpdateRuleParametersRequest;
use App\Http\Requests\UpdateShiftTypeRequest;
use App\Models\AuditLog;
use App\Models\CoverageRule;
use App\Models\RuleConfig;
use App\Models\ShiftType;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class RuleSettingsController extends Controller
{
    public function index(): Response
    {
        $shiftTypes = ShiftType::query()->orderedByShift()->get();

        $coverageRules = CoverageRule::query()->get();

        $coverage = $shiftTypes
            ->flatMap(fn (ShiftType $shiftType) => collect(range(0, 6))->map(fn (int $weekday) => [
                'shift_type_id' => $shiftType->id,
                'weekday' => $weekday,
                'required' => $coverageRules
                    ->first(fn (CoverageRule $rule) => $rule->shift_type_id === $shiftType->id && $rule->weekday === $weekday)
                    ?->required ?? 0,
            ]))
            ->values();

        $defaults = RuleConfig::defaults();

        return Inertia::render('admin/rules/index', [
            'shift_types' => $shiftTypes->map(fn (ShiftType $shiftType) => [
                'id' => $shiftType->id,
                'code' => $shiftType->code,
                'name' => $shiftType->name,
                'starts_at' => substr($shiftType->starts_at, 0, 5),
                'ends_at' => substr($shiftType->ends_at, 0, 5),
                'hours' => $shiftType->hours,
                'color' => $shiftType->color,
            ]),
            'coverage' => $coverage,
            'rule_configs' => [
                'hour_bank_weekly_tolerance' => RuleConfig::get('hour_bank_weekly_tolerance', $defaults['hour_bank_weekly_tolerance']),
                'max_consecutive_work_days' => RuleConfig::get('max_consecutive_work_days', $defaults['max_consecutive_work_days']),
                'ff_window_weeks' => RuleConfig::get('ff_window_weeks', $defaults['ff_window_weeks']),
                'ff_monthly' => RuleConfig::get('ff_monthly', $defaults['ff_monthly']),
            ],
        ]);
    }

    public function updateCoverage(UpdateCoverageRequest $request): RedirectResponse
    {
        $coverage = $request->validated('coverage');

        foreach ($coverage as $row) {
            CoverageRule::query()->updateOrCreate(
                ['shift_type_id' => $row['shift_type_id'], 'weekday' => $row['weekday']],
                ['required' => (int) $row['required']]
            );
        }

        AuditLog::record('rules.updated', null, ['section' => 'coverage', 'coverage' => $coverage]);

        return back()->with('success', 'Cobertura atualizada.');
    }

    public function updateParameters(UpdateRuleParametersRequest $request): RedirectResponse
    {
        $data = $request->validated();

        foreach ($data as $key => $value) {
            RuleConfig::set($key, $value);
        }

        AuditLog::record('rules.updated', null, ['section' => 'parameters', ...$data]);

        return back()->with('success', 'Parâmetros atualizados.');
    }

    public function updateShiftType(UpdateShiftTypeRequest $request, ShiftType $shiftType): RedirectResponse
    {
        $shiftType->update($request->validated());

        AuditLog::record('rules.updated', $shiftType, ['section' => 'shift_type', ...$request->validated()]);

        return back()->with('success', 'Turno atualizado.');
    }
}
