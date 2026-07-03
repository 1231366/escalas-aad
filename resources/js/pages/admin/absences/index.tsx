import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type Absence, type AbsenceEmployeeOption, type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { Page } from '@inertiajs/core';
import { AlertTriangle, Plus, RotateCw, Trash2 } from 'lucide-react';
import { FormEvent, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Ausências', href: '/admin/ausencias' }];

const typeBadge: Record<Absence['type'], { variant: 'default' | 'secondary' | 'destructive' | 'outline' }> = {
    SICK: { variant: 'secondary' },
    UNJUSTIFIED: { variant: 'destructive' },
    OTHER: { variant: 'outline' },
};

const reoptimizationBadge: Record<NonNullable<Absence['reoptimization_status']>, { label: string; variant: 'default' | 'secondary' | 'destructive' | 'outline' }> = {
    FEASIBLE: { label: 'Re-otimizada', variant: 'secondary' },
    INFEASIBLE: { label: 'Inviável', variant: 'destructive' },
    UNAVAILABLE: { label: 'Solver indisponível', variant: 'outline' },
};

function formatDate(date: string): string {
    return new Date(`${date}T00:00:00`).toLocaleDateString('pt-PT');
}

export default function AbsencesIndex({ employees, absences }: { employees: AbsenceEmployeeOption[]; absences: Absence[] }) {
    const [open, setOpen] = useState(false);
    const [gapsWarning, setGapsWarning] = useState<{ employeeName: string; gaps: Absence['coverage_gaps'] } | null>(null);

    const { data, setData, post, processing, errors, reset } = useForm({
        employee_id: '',
        start_date: '',
        end_date: '',
        type: 'SICK',
        note: '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();

        const submitted = { ...data };

        post('/admin/ausencias', {
            preserveScroll: true,
            onSuccess: (page: Page) => {
                reset();
                setOpen(false);

                const fresh = (page.props.absences as Absence[] | undefined) ?? [];
                const created = fresh.find(
                    (absence) =>
                        String(absence.employee_id) === String(submitted.employee_id) &&
                        absence.start_date === submitted.start_date &&
                        absence.end_date === submitted.end_date,
                );

                if (created && created.coverage_gaps.length > 0) {
                    setGapsWarning({ employeeName: created.employee_name, gaps: created.coverage_gaps });
                }
            },
        });
    };

    const destroy = (absence: Absence) => {
        if (!confirm(`Remover a ausência de ${absence.employee_name}?`)) return;
        router.delete(`/admin/ausencias/${absence.id}`, { preserveScroll: true });
    };

    const reoptimize = (absence: Absence) => {
        if (!absence.reoptimizable_from) return;
        if (
            !confirm(
                `Re-otimizar a escala a partir de ${formatDate(absence.reoptimizable_from)}? Os turnos futuros da equipa podem mudar; os dias anteriores ficam intactos.`,
            )
        ) {
            return;
        }
        router.post(`/admin/ausencias/${absence.id}/reotimizar`, {}, { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Ausências" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold">Ausências</h1>
                        <p className="text-muted-foreground text-sm">
                            Regista baixas e faltas. Se a pessoa já tiver turnos numa escala publicada, avisamos dos buracos de cobertura criados.
                        </p>
                    </div>

                    <Dialog open={open} onOpenChange={setOpen}>
                        <DialogTrigger asChild>
                            <Button>
                                <Plus className="size-4" /> Nova ausência
                            </Button>
                        </DialogTrigger>
                        <DialogContent className="sm:max-w-md">
                            <DialogHeader>
                                <DialogTitle>Registar ausência</DialogTitle>
                                <DialogDescription>Baixa médica, falta ou outro motivo — H10 passa a respeitar este intervalo.</DialogDescription>
                            </DialogHeader>
                            <form onSubmit={submit} className="grid gap-4">
                                <div className="grid gap-2">
                                    <Label>Funcionária</Label>
                                    <Select value={data.employee_id} onValueChange={(v) => setData('employee_id', v)}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="Escolhe a funcionária" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {employees.map((employee) => (
                                                <SelectItem key={employee.id} value={String(employee.id)}>
                                                    {employee.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.employee_id} />
                                </div>
                                <div className="grid grid-cols-2 gap-4">
                                    <div className="grid gap-2">
                                        <Label htmlFor="start_date">Início</Label>
                                        <Input
                                            id="start_date"
                                            type="date"
                                            value={data.start_date}
                                            onChange={(e) => setData('start_date', e.target.value)}
                                            required
                                        />
                                        <InputError message={errors.start_date} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="end_date">Fim</Label>
                                        <Input
                                            id="end_date"
                                            type="date"
                                            value={data.end_date}
                                            onChange={(e) => setData('end_date', e.target.value)}
                                            required
                                        />
                                        <InputError message={errors.end_date} />
                                    </div>
                                </div>
                                <div className="grid gap-2">
                                    <Label>Tipo</Label>
                                    <Select value={data.type} onValueChange={(v) => setData('type', v)}>
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="SICK">Baixa médica</SelectItem>
                                            <SelectItem value="UNJUSTIFIED">Falta</SelectItem>
                                            <SelectItem value="OTHER">Outro</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.type} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="note">Nota (opcional)</Label>
                                    <Input id="note" value={data.note} onChange={(e) => setData('note', e.target.value)} />
                                    <InputError message={errors.note} />
                                </div>
                                <DialogFooter>
                                    <Button type="submit" disabled={processing}>
                                        Registar
                                    </Button>
                                </DialogFooter>
                            </form>
                        </DialogContent>
                    </Dialog>
                </div>

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="bg-muted/50 text-muted-foreground border-b text-left">
                                <th className="p-3 font-medium">Funcionária</th>
                                <th className="p-3 font-medium">Tipo</th>
                                <th className="p-3 font-medium">Período</th>
                                <th className="p-3 font-medium">Cobertura</th>
                                <th className="p-3 font-medium">Re-otimização</th>
                                <th className="p-3 text-right font-medium">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            {absences.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="text-muted-foreground p-8 text-center">
                                        Ainda não há ausências registadas.
                                    </td>
                                </tr>
                            )}
                            {absences.map((absence) => (
                                <tr key={absence.id} className="border-b align-top last:border-0">
                                    <td className="p-3 font-medium">
                                        {absence.employee_name}
                                        {absence.note && <p className="text-muted-foreground mt-0.5 text-xs font-normal">{absence.note}</p>}
                                    </td>
                                    <td className="p-3">
                                        <Badge variant={typeBadge[absence.type].variant}>{absence.type_label}</Badge>
                                    </td>
                                    <td className="text-muted-foreground p-3">
                                        {formatDate(absence.start_date)} – {formatDate(absence.end_date)}
                                    </td>
                                    <td className="p-3">
                                        {absence.coverage_gaps.length > 0 ? (
                                            <button
                                                type="button"
                                                onClick={() => setGapsWarning({ employeeName: absence.employee_name, gaps: absence.coverage_gaps })}
                                                className="inline-flex items-center gap-1 text-destructive underline-offset-2 hover:underline"
                                            >
                                                <AlertTriangle className="size-3.5" />
                                                {absence.coverage_gaps.length} buraco{absence.coverage_gaps.length > 1 ? 's' : ''}
                                            </button>
                                        ) : (
                                            <span className="text-muted-foreground">—</span>
                                        )}
                                    </td>
                                    <td className="p-3">
                                        {absence.reoptimizable_from && (
                                            <Button variant="outline" size="sm" onClick={() => reoptimize(absence)}>
                                                <RotateCw className="size-4" /> Re-otimizar a partir de {formatDate(absence.reoptimizable_from)}
                                            </Button>
                                        )}
                                        {!absence.reoptimizable_from && absence.reoptimization_status && (
                                            <div className="flex flex-col gap-1">
                                                <Badge variant={reoptimizationBadge[absence.reoptimization_status].variant}>
                                                    {reoptimizationBadge[absence.reoptimization_status].label}
                                                </Badge>
                                                {absence.reoptimization_status === 'INFEASIBLE' && absence.reoptimization_conflicts && (
                                                    <ul className="text-muted-foreground list-disc pl-4 text-xs">
                                                        {absence.reoptimization_conflicts.map((conflict, i) => (
                                                            <li key={i}>{conflict.message}</li>
                                                        ))}
                                                    </ul>
                                                )}
                                            </div>
                                        )}
                                        {!absence.reoptimizable_from && !absence.reoptimization_status && (
                                            <span className="text-muted-foreground">—</span>
                                        )}
                                    </td>
                                    <td className="p-3 text-right">
                                        <Button variant="ghost" size="sm" title="Remover" onClick={() => destroy(absence)}>
                                            <Trash2 className="size-4 text-red-500" />
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            <Dialog open={gapsWarning !== null} onOpenChange={(v) => !v && setGapsWarning(null)}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <AlertTriangle className="text-destructive size-5" /> Buracos de cobertura
                        </DialogTitle>
                        <DialogDescription>
                            {gapsWarning?.employeeName} tinha turnos atribuídos nestes dias — sem ela, a cobertura fica abaixo do exigido.
                        </DialogDescription>
                    </DialogHeader>
                    <ul className="grid gap-1.5 text-sm">
                        {gapsWarning?.gaps.map((gap) => (
                            <li key={`${gap.date}-${gap.shift_code}`} className="flex items-center justify-between rounded-md border px-3 py-2">
                                <span>
                                    {gap.shift_code} a {formatDate(gap.date)}
                                </span>
                                <span className="text-destructive font-medium">
                                    {gap.after}/{gap.required}
                                </span>
                            </li>
                        ))}
                    </ul>
                    <DialogFooter>
                        <Button onClick={() => setGapsWarning(null)}>Entendido</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
