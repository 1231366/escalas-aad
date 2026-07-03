import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import AppLayout from '@/layouts/app-layout';
import {
    type BreadcrumbItem,
    type ScheduleDate,
    type ScheduleDayFooter,
    type ScheduleEmployeeRow,
    type ScheduleMeta,
    type ShiftType,
    type SolverViolation,
} from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, Archive, RefreshCw, Rocket } from 'lucide-react';
import { useState } from 'react';

interface Props {
    schedule: ScheduleMeta;
    shift_types: ShiftType[];
    dates: ScheduleDate[];
    employees: ScheduleEmployeeRow[];
    day_footers: ScheduleDayFooter[];
    cell_violations?: SolverViolation[] | null;
    cell_error?: string | null;
}

const monthLabel = (dateStr: string) => new Date(dateStr).toLocaleDateString('pt-PT', { month: 'long', year: 'numeric' });

const shortDate = (dateStr: string) => {
    const [, month, day] = dateStr.split('-');
    return `${day}/${month}`;
};

const infeasibleTitle: Record<string, string> = {
    INFEASIBLE: 'Não foi possível gerar a escala',
    TIMEOUT: 'O solver não conseguiu terminar a tempo',
    UNAVAILABLE: 'O solver está indisponível',
};

export default function ScheduleShow({ schedule, shift_types, dates, employees, day_footers, cell_violations, cell_error }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Escalas', href: '/admin/escalas' },
        { title: monthLabel(schedule.period_start), href: `/admin/escalas/${schedule.id}` },
    ];

    const [pendingCell, setPendingCell] = useState<string | null>(null);

    const shiftByCode = Object.fromEntries(shift_types.map((s) => [s.code, s]));
    const employeeNameById = Object.fromEntries(employees.map((e) => [e.employee_id, e.name]));
    const stats = schedule.solver_stats;
    const isInfeasible = !!stats && stats.status !== 'FEASIBLE';
    const isDraft = schedule.status === 'DRAFT';

    const generate = () => router.post(`/admin/escalas/${schedule.id}/gerar`, {}, { preserveScroll: true });
    const publish = () => router.post(`/admin/escalas/${schedule.id}/publicar`, {}, { preserveScroll: true });
    const archive = () => router.post(`/admin/escalas/${schedule.id}/arquivar`, {}, { preserveScroll: true });

    const updateCell = (employeeId: number, date: string, shiftTypeId: number | null) => {
        const cellKey = `${employeeId}|${date}`;

        router.patch(
            `/admin/escalas/${schedule.id}/celula`,
            { employee_id: employeeId, date, shift_type_id: shiftTypeId },
            {
                preserveScroll: true,
                onStart: () => setPendingCell(cellKey),
                onFinish: () => setPendingCell(null),
            },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Escala — ${monthLabel(schedule.period_start)}`} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold capitalize">{monthLabel(schedule.period_start)}</h1>
                        <p className="text-muted-foreground text-sm">
                            {schedule.period_start} a {schedule.period_end}
                        </p>
                    </div>

                    <div className="flex items-center gap-2">
                        {schedule.status === 'DRAFT' && (
                            <>
                                <Badge>Rascunho</Badge>
                                <Button variant="outline" size="sm" onClick={generate}>
                                    <RefreshCw className="size-4" /> Gerar
                                </Button>
                                <Button size="sm" onClick={publish} disabled={stats?.status !== 'FEASIBLE'}>
                                    <Rocket className="size-4" /> Publicar
                                </Button>
                            </>
                        )}
                        {schedule.status === 'PUBLISHED' && (
                            <>
                                <Badge variant="secondary">Publicada</Badge>
                                <Button variant="outline" size="sm" onClick={archive}>
                                    <Archive className="size-4" /> Arquivar
                                </Button>
                            </>
                        )}
                        {schedule.status === 'ARCHIVED' && <Badge variant="outline">Arquivada</Badge>}
                    </div>
                </div>

                {isInfeasible && (
                    <Alert variant="destructive">
                        <AlertTriangle className="size-4" />
                        <AlertTitle>{infeasibleTitle[stats!.status] ?? 'Não foi possível gerar a escala'}</AlertTitle>
                        <AlertDescription>
                            <p className="mb-2">
                                Ajusta a cobertura ou os parâmetros em{' '}
                                <Link href="/admin/regras" className="underline">
                                    Regras
                                </Link>{' '}
                                e gera novamente.
                            </p>
                            {stats?.conflicts && stats.conflicts.length > 0 && (
                                <ul className="list-disc space-y-1 pl-5">
                                    {stats.conflicts.map((conflict, i) => (
                                        <li key={i}>
                                            <span className="font-mono font-semibold">{conflict.rule}</span> — {conflict.message}
                                            {conflict.date && ` (${conflict.date})`}
                                        </li>
                                    ))}
                                </ul>
                            )}
                            {stats?.error && <p className="text-xs">{stats.error}</p>}
                        </AlertDescription>
                    </Alert>
                )}

                {cell_violations && cell_violations.length > 0 && (
                    <Alert variant="destructive">
                        <AlertTriangle className="size-4" />
                        <AlertTitle>Alteração rejeitada pelo solver</AlertTitle>
                        <AlertDescription>
                            <ul className="list-disc space-y-1 pl-5">
                                {cell_violations.map((violation, i) => (
                                    <li key={i}>
                                        <span className="font-mono font-semibold">{violation.rule}</span> — {violation.message}
                                        {violation.date && ` a ${shortDate(violation.date)}`}
                                        {violation.employee_id != null &&
                                            employeeNameById[violation.employee_id] &&
                                            ` para ${employeeNameById[violation.employee_id]}`}
                                    </li>
                                ))}
                            </ul>
                        </AlertDescription>
                    </Alert>
                )}

                {cell_error && (
                    <Alert variant="destructive">
                        <AlertTriangle className="size-4" />
                        <AlertTitle>Não foi possível validar a alteração</AlertTitle>
                        <AlertDescription>{cell_error}</AlertDescription>
                    </Alert>
                )}

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full border-collapse text-sm">
                        <thead>
                            <tr>
                                <th className="bg-background sticky top-0 left-0 z-20 border-r border-b p-2 text-left font-medium">Funcionária</th>
                                {dates.map((date) => (
                                    <th
                                        key={date.date}
                                        className={`sticky top-0 z-10 min-w-10 border-b p-1 text-center text-xs font-medium ${
                                            date.is_weekend ? 'bg-muted' : 'bg-background'
                                        }`}
                                    >
                                        <div>{date.weekday_label}</div>
                                        <div>{date.day}</div>
                                    </th>
                                ))}
                                <th className="bg-background sticky top-0 z-10 border-b border-l p-2 text-center font-medium">h/mês</th>
                                <th className="bg-background sticky top-0 z-10 border-b p-2 text-center font-medium">h/sem.</th>
                                <th className="bg-background sticky top-0 z-10 border-b p-2 text-center font-medium">Folgas</th>
                                <th className="bg-background sticky top-0 z-10 border-b p-2 text-center font-medium">FDS</th>
                            </tr>
                        </thead>
                        <tbody>
                            {employees.length === 0 && (
                                <tr>
                                    <td colSpan={dates.length + 5} className="text-muted-foreground p-8 text-center">
                                        Sem funcionárias ativas.
                                    </td>
                                </tr>
                            )}
                            {employees.map((employee) => (
                                <tr key={employee.employee_id} className="border-b last:border-0">
                                    <td className="bg-background sticky left-0 z-10 border-r p-2 font-medium">{employee.name}</td>
                                    {employee.cells.map((cell) => {
                                        const shiftType = cell.shift_code ? shiftByCode[cell.shift_code] : null;
                                        const cellKey = `${employee.employee_id}|${cell.date}`;
                                        const isPending = pendingCell === cellKey;

                                        const cellBadge = shiftType ? (
                                            <span
                                                className="block rounded py-1 text-xs font-semibold text-white"
                                                style={{ backgroundColor: shiftType.color }}
                                            >
                                                {shiftType.code}
                                            </span>
                                        ) : (
                                            <span className="text-muted-foreground bg-muted/40 block rounded py-1 text-xs">
                                                {cell.is_day_off ? 'F' : ''}
                                            </span>
                                        );

                                        if (!isDraft) {
                                            return (
                                                <td key={cell.date} className="p-1 text-center">
                                                    {cellBadge}
                                                </td>
                                            );
                                        }

                                        return (
                                            <td key={cell.date} className="p-1 text-center">
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger asChild>
                                                        <button
                                                            type="button"
                                                            disabled={isPending}
                                                            aria-label={`Editar turno de ${employee.name} em ${cell.date}`}
                                                            className="hover:ring-ring w-full cursor-pointer rounded disabled:cursor-wait disabled:opacity-50 hover:ring-2"
                                                        >
                                                            {cellBadge}
                                                        </button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="center">
                                                        <DropdownMenuLabel className="text-muted-foreground text-xs font-normal">
                                                            {employee.name} — {shortDate(cell.date)}
                                                        </DropdownMenuLabel>
                                                        <DropdownMenuSeparator />
                                                        {shift_types.map((st) => (
                                                            <DropdownMenuItem
                                                                key={st.id}
                                                                onSelect={() => updateCell(employee.employee_id, cell.date, st.id)}
                                                            >
                                                                <span className="size-2 rounded-full" style={{ backgroundColor: st.color }} />
                                                                {st.code} — {st.name}
                                                            </DropdownMenuItem>
                                                        ))}
                                                        <DropdownMenuItem onSelect={() => updateCell(employee.employee_id, cell.date, null)}>
                                                            <span className="bg-muted-foreground/40 size-2 rounded-full" />
                                                            Folga
                                                        </DropdownMenuItem>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </td>
                                        );
                                    })}
                                    <td className="border-l p-2 text-center">{employee.total_hours}</td>
                                    <td className="p-2 text-center">{employee.avg_weekly_hours}</td>
                                    <td className="p-2 text-center">{employee.days_off}</td>
                                    <td className="p-2 text-center">{employee.weekends_worked}</td>
                                </tr>
                            ))}
                        </tbody>
                        <tfoot>
                            {shift_types.map((shiftType) => (
                                <tr key={shiftType.id} className="border-t">
                                    <td className="bg-muted/30 sticky left-0 z-10 border-r p-2 text-xs font-medium">
                                        <span className="inline-flex items-center gap-1">
                                            <span className="size-2 rounded-full" style={{ backgroundColor: shiftType.color }} />
                                            {shiftType.code}
                                        </span>
                                    </td>
                                    {day_footers.map((footer) => {
                                        const cell = footer.shifts.find((s) => s.shift_type_id === shiftType.id);

                                        return (
                                            <td
                                                key={footer.date}
                                                className={`p-1 text-center text-xs ${cell?.ok ? 'text-green-600' : 'font-semibold text-red-600'}`}
                                            >
                                                {cell ? `${cell.actual}/${cell.required}` : '—'}
                                            </td>
                                        );
                                    })}
                                    <td colSpan={4} />
                                </tr>
                            ))}
                        </tfoot>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
