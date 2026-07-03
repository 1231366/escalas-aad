<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Models\AuditLog;
use App\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CRUD de funcionárias "sem acesso" (PRD: mockar a escala sem dar login).
 * Uma Employee sem user_id nunca teve/precisa de conta — entra na geração
 * e na grelha como qualquer outra, só não pode entrar na app.
 */
class EmployeeController extends Controller
{
    public function index(): Response
    {
        $employees = Employee::query()
            ->with('user:id,email')
            ->orderBy('name')
            ->get()
            ->map(fn (Employee $employee) => [
                'id' => $employee->id,
                'name' => $employee->name,
                'regime' => $employee->regime->value,
                'regime_label' => $employee->regime->label(),
                'contract' => $employee->contract->value,
                'contract_label' => $employee->contract->label(),
                'fixa_noite' => $employee->fixa_noite,
                'active' => $employee->active,
                'has_account' => $employee->user_id !== null,
                'email' => $employee->user?->email,
            ]);

        return Inertia::render('admin/employees/index', [
            'employees' => $employees,
        ]);
    }

    public function store(StoreEmployeeRequest $request): RedirectResponse
    {
        $employee = Employee::create([
            ...$request->validated(),
            'user_id' => null,
            'active' => true,
        ]);

        AuditLog::record('employee.created', $employee, ['name' => $employee->name, 'has_account' => false]);

        return back()->with('success', 'Funcionária criada sem acesso ao sistema — já entra na geração de escalas.');
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $employee->update($request->validated());

        AuditLog::record('employee.updated', $employee, $request->validated());

        return back()->with('success', 'Perfil atualizado.');
    }

    public function destroy(Employee $employee): RedirectResponse
    {
        abort_if($employee->user_id !== null, 400, 'Não é possível remover uma funcionária com conta — desativa-a em vez disso.');

        AuditLog::record('employee.deleted', $employee, ['name' => $employee->name]);
        $employee->delete();

        return back()->with('success', 'Funcionária removida.');
    }
}
