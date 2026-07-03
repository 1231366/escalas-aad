import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import {
    type AdminDashboardStats,
    type BreadcrumbItem,
    type EmployeeDashboardStats,
    type EquityEmployee,
    type ShiftType,
    type Viability,
} from '@/types';
import { Head, Link } from '@inertiajs/react';
import { AlertTriangle, CalendarCheck, CheckCircle2, ClipboardList, Moon, Plane, Repeat, UserPlus, XCircle } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

interface Props {
    viability: Viability | null;
    admin_stats: AdminDashboardStats | null;
    employee_stats: EmployeeDashboardStats | null;
    shift_types: ShiftType[];
}

export default function Dashboard({ viability, admin_stats, employee_stats, shift_types }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                {admin_stats ? (
                    <>
                        {viability && <ViabilityCard viability={viability} />}
                        <ThisMonthCard stats={admin_stats} />
                        <EquityCard equity={admin_stats.equity} />
                    </>
                ) : (
                    employee_stats && <EmployeeDashboard stats={employee_stats} shiftTypes={shift_types} />
                )}
            </div>
        </AppLayout>
    );
}

const monthScheduleStatus: Record<string, { label: string; className: string }> = {
    DRAFT: { label: 'Rascunho', className: 'border-transparent bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300' },
    PUBLISHED: { label: 'Publicada', className: 'border-transparent bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300' },
    ARCHIVED: { label: 'Arquivada', className: 'border-transparent bg-muted text-muted-foreground' },
};

function ThisMonthCard({ stats }: { stats: AdminDashboardStats }) {
    const { schedule, pending_swaps, pending_vacations, pending_invitations } = stats.this_month;
    const status = schedule ? monthScheduleStatus[schedule.status] : null;

    return (
        <Card>
            <CardHeader className="flex-row items-start justify-between gap-4 space-y-0">
                <div>
                    <CardTitle>Este mês</CardTitle>
                    <CardDescription>Estado da escala e pedidos à espera de decisão.</CardDescription>
                </div>
                {status && <Badge className={cn('gap-1.5', status.className)}>{status.label}</Badge>}
            </CardHeader>
            <CardContent className="grid gap-4 sm:grid-cols-4">
                <div className="rounded-lg border p-4">
                    <p className="text-muted-foreground text-xs">Escala do mês</p>
                    {schedule ? (
                        <>
                            <p className="text-lg font-semibold capitalize">{schedule.label}</p>
                            <Link href={`/admin/escalas/${schedule.id}`} className="text-primary text-xs hover:underline">
                                Ver escala
                            </Link>
                        </>
                    ) : (
                        <>
                            <p className="text-sm">Ainda não existe</p>
                            <Link href="/admin/escalas" className="text-primary text-xs hover:underline">
                                Criar escala
                            </Link>
                        </>
                    )}
                </div>

                <StatTile icon={Repeat} label="Trocas pendentes" value={pending_swaps} />
                <StatTile icon={Plane} label="Férias pendentes" value={pending_vacations} />
                <StatTile icon={UserPlus} label="Convites pendentes" value={pending_invitations} href="/admin/convites" />
            </CardContent>
        </Card>
    );
}

function StatTile({ icon: Icon, label, value, href }: { icon: typeof Repeat; label: string; value: number; href?: string }) {
    const content = (
        <div className="rounded-lg border p-4">
            <div className="text-muted-foreground flex items-center gap-1.5 text-xs">
                <Icon className="size-3.5" />
                {label}
            </div>
            <p className="text-lg font-semibold">{value}</p>
        </div>
    );

    return href ? (
        <Link href={href} className="block transition-opacity hover:opacity-80">
            {content}
        </Link>
    ) : (
        content
    );
}

function EquityCard({ equity }: { equity: AdminDashboardStats['equity'] }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Equidade</CardTitle>
                <CardDescription>
                    {equity
                        ? `Horas, fins de semana, folgas e banco de horas na escala de ${equity.schedule.label} (última publicada).`
                        : 'Horas, fins de semana, folgas e banco de horas da última escala publicada.'}
                </CardDescription>
            </CardHeader>
            <CardContent>
                {equity && equity.employees.length > 0 ? (
                    <div className="flex flex-col gap-3">
                        <div className="text-muted-foreground hidden gap-3 px-1 text-xs sm:flex">
                            <div className="w-36 shrink-0">Funcionária</div>
                            <div className="flex-1">Horas totais</div>
                            <div className="w-20 shrink-0 text-right">Fins de semana</div>
                            <div className="w-16 shrink-0 text-right">Folgas</div>
                            <div className="w-20 shrink-0 text-right">Banco de horas</div>
                        </div>
                        {equity.employees.map((employee) => (
                            <EquityRow
                                key={employee.employee_id}
                                employee={employee}
                                isMax={employee.employee_id === equity.max_hours_employee_id}
                                isMin={employee.employee_id === equity.min_hours_employee_id}
                            />
                        ))}
                    </div>
                ) : (
                    <div className="text-muted-foreground rounded-xl border p-8 text-center text-sm">
                        Ainda não há uma escala publicada. Quando publicares a primeira, a equidade da equipa aparece aqui.
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

function EquityRow({ employee, isMax, isMin }: { employee: EquityEmployee; isMax: boolean; isMin: boolean }) {
    const balancePositive = employee.hour_bank_balance >= 0;

    return (
        <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-3">
            <div className="flex w-36 shrink-0 items-center gap-1.5 truncate text-sm font-medium">
                <span className="truncate">{employee.name}</span>
                {isMax && (
                    <Badge variant="outline" className="border-amber-300 px-1 text-[10px] text-amber-700 dark:text-amber-400">
                        máx
                    </Badge>
                )}
                {isMin && (
                    <Badge variant="outline" className="border-sky-300 px-1 text-[10px] text-sky-700 dark:text-sky-400">
                        mín
                    </Badge>
                )}
            </div>

            <div className="flex flex-1 items-center gap-2">
                <div className="bg-muted h-2.5 flex-1 rounded">
                    <div
                        className="bg-primary h-2.5 rounded-r"
                        style={{ width: `${Math.max(employee.bar_pct, 2)}%` }}
                        role="img"
                        aria-label={`${employee.total_hours} horas`}
                    />
                </div>
                <span className="w-14 shrink-0 text-right text-sm tabular-nums">{employee.total_hours}h</span>
            </div>

            <div className="text-muted-foreground w-20 shrink-0 text-right text-xs tabular-nums sm:text-sm">
                {employee.weekends_worked}
            </div>
            <div className="text-muted-foreground w-16 shrink-0 text-right text-xs tabular-nums sm:text-sm">{employee.days_off}</div>
            <div
                className={cn(
                    'w-20 shrink-0 text-right text-xs font-medium tabular-nums sm:text-sm',
                    balancePositive ? 'text-green-700 dark:text-green-400' : 'text-red-700 dark:text-red-400',
                )}
            >
                {employee.hour_bank_label}
            </div>
        </div>
    );
}

function EmployeeDashboard({ stats, shiftTypes }: { stats: EmployeeDashboardStats; shiftTypes: ShiftType[] }) {
    const shiftByCode = Object.fromEntries(shiftTypes.map((s) => [s.code, s]));
    const nextShiftType = stats.next_shift ? shiftByCode[stats.next_shift.shift_code] : null;

    return (
        <div className="grid gap-4">
            <Card>
                <CardHeader>
                    <CardTitle>Próximo turno</CardTitle>
                    <CardDescription>O teu próximo turno na escala publicada.</CardDescription>
                </CardHeader>
                <CardContent>
                    {stats.next_shift ? (
                        <div className="flex items-center gap-3 rounded-lg border p-4">
                            <span
                                className="flex size-10 shrink-0 items-center justify-center rounded-full text-sm font-semibold text-white"
                                style={{ backgroundColor: nextShiftType?.color ?? '#71717a' }}
                            >
                                {stats.next_shift.shift_code}
                            </span>
                            <div>
                                <p className="text-lg font-semibold capitalize">
                                    {new Date(stats.next_shift.date).toLocaleDateString('pt-PT', {
                                        weekday: 'long',
                                        day: 'numeric',
                                        month: 'long',
                                    })}
                                </p>
                                <p className="text-muted-foreground text-sm">{nextShiftType?.name ?? stats.next_shift.shift_code}</p>
                            </div>
                        </div>
                    ) : (
                        <div className="text-muted-foreground rounded-xl border p-8 text-center text-sm">
                            Sem turnos atribuídos nos próximos dias na escala publicada.
                        </div>
                    )}
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>A minha semana</CardTitle>
                    <CardDescription>Semana atual, da escala publicada.</CardDescription>
                </CardHeader>
                <CardContent>
                    {stats.current_week.length > 0 ? (
                        <div className="grid grid-cols-7 gap-2">
                            {stats.current_week.map((cell) => {
                                const shiftType = cell.shift_code ? shiftByCode[cell.shift_code] : null;

                                return (
                                    <div
                                        key={cell.date}
                                        className={cn(
                                            'flex flex-col items-center gap-1 rounded-lg border p-2',
                                            cell.is_today && 'border-primary',
                                        )}
                                    >
                                        <span className="text-muted-foreground text-xs">{cell.weekday_label}</span>
                                        <span className="text-sm font-medium">{cell.day}</span>
                                        {shiftType ? (
                                            <span
                                                className="w-full rounded py-1 text-center text-xs font-semibold text-white"
                                                style={{ backgroundColor: shiftType.color }}
                                            >
                                                {shiftType.code}
                                            </span>
                                        ) : (
                                            <span className="text-muted-foreground bg-muted/40 w-full rounded py-1 text-center text-xs">
                                                {cell.is_day_off ? 'F' : '—'}
                                            </span>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    ) : (
                        <div className="text-muted-foreground rounded-xl border p-8 text-center text-sm">
                            Ainda não há uma escala publicada para esta semana.
                        </div>
                    )}
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Os meus pedidos</CardTitle>
                    <CardDescription>Pedidos teus à espera de decisão.</CardDescription>
                </CardHeader>
                <CardContent className="grid gap-4 sm:grid-cols-2">
                    <StatTile icon={ClipboardList} label="Trocas pendentes" value={stats.pending_swaps} />
                    <StatTile icon={CalendarCheck} label="Férias pendentes" value={stats.pending_vacations} />
                </CardContent>
            </Card>
        </div>
    );
}

const statusConfig: Record<Viability['status'], { label: string; className: string; icon: typeof CheckCircle2 }> = {
    ok: {
        label: 'Viável',
        className: 'border-transparent bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300',
        icon: CheckCircle2,
    },
    tight: {
        label: 'Só fecha com banco de horas',
        className: 'border-transparent bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
        icon: AlertTriangle,
    },
    deficit: {
        label: 'Défice',
        className: 'border-transparent bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300',
        icon: XCircle,
    },
};

function ViabilityCard({ viability }: { viability: Viability }) {
    const status = statusConfig[viability.status];
    const StatusIcon = status.icon;
    const deficitContratual = -viability.balance.contractual.shifts_per_week;

    return (
        <Card>
            <CardHeader className="flex-row items-start justify-between gap-4 space-y-0">
                <div>
                    <CardTitle>Viabilidade da escala</CardTitle>
                    <CardDescription>Procura de turnos vs. oferta da equipa, por semana (ADR-0003).</CardDescription>
                </div>
                <Badge className={cn('gap-1.5', status.className)}>
                    <StatusIcon className="size-3.5" />
                    {status.label}
                </Badge>
            </CardHeader>
            <CardContent className="grid gap-6">
                <div className="grid gap-4 sm:grid-cols-3">
                    <Stat label="Procura" value={`${viability.demand.shifts_per_week} turnos/semana`} hint={`${viability.demand.hours_per_week}h`} />
                    <Stat
                        label="Oferta contratual"
                        value={`${viability.supply.contractual.shifts_per_week} turnos/semana`}
                        hint={`${viability.supply.contractual.hours_per_week}h · ${viability.employees_count} funcionárias`}
                    />
                    <Stat
                        label="Oferta com banco de horas"
                        value={`${viability.supply.with_hour_bank.shifts_per_week} turnos/semana`}
                        hint={`+${viability.hour_bank_weekly_tolerance}h/semana por pessoa`}
                    />
                </div>

                <div
                    className={cn(
                        'rounded-lg border p-4 text-sm',
                        deficitContratual > 0
                            ? 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200'
                            : 'border-green-200 bg-green-50 text-green-900 dark:border-green-900 dark:bg-green-950/40 dark:text-green-200',
                    )}
                >
                    {deficitContratual > 0 ? (
                        <p>
                            <strong>Défice contratual:</strong> faltam {deficitContratual.toFixed(1)} turnos/semana (
                            {Math.abs(viability.balance.contractual.hours_per_week).toFixed(1)}h) para cobrir a procura só com os contratos atuais.{' '}
                            {viability.balance.with_hour_bank.shifts_per_week >= 0
                                ? 'O banco de horas configurado é suficiente para fechar a escala.'
                                : `Mesmo com banco de horas ainda faltam ${(-viability.balance.with_hour_bank.shifts_per_week).toFixed(1)} turnos/semana.`}
                        </p>
                    ) : (
                        <p>
                            <strong>Folga contratual:</strong> a equipa cobre a procura sem precisar de banco de horas (
                            {viability.balance.contractual.shifts_per_week.toFixed(1)} turnos/semana de margem).
                        </p>
                    )}
                </div>

                <div className="flex flex-col gap-2 rounded-lg border p-4 text-sm">
                    <div className="flex items-center gap-2 font-medium">
                        <Moon className="size-4" />
                        Pool de noite
                    </div>
                    <p className="text-muted-foreground">
                        {viability.night.pool_size} funcionária(s) elegível(is) para a noite (mínimo recomendado: {viability.night.min_pool_size}{' '}
                        para 2N/dia em ciclo NNNFF) · {viability.night.required_shifts_per_week} turnos N exigidos/semana.
                    </p>
                    <Badge
                        variant="outline"
                        className={cn(
                            'w-fit',
                            viability.night.pool_ok
                                ? 'border-green-200 text-green-700 dark:border-green-900 dark:text-green-300'
                                : 'border-red-200 text-red-700 dark:border-red-900 dark:text-red-300',
                        )}
                    >
                        {viability.night.pool_ok ? 'Pool de noite suficiente' : 'Pool de noite insuficiente'}
                    </Badge>
                </div>

                {viability.suggestions.length > 0 && (
                    <div className="flex flex-col gap-2">
                        <h3 className="text-sm font-medium">Sugestões</h3>
                        <ul className="text-muted-foreground list-inside list-disc space-y-1 text-sm">
                            {viability.suggestions.map((suggestion) => (
                                <li key={suggestion}>{suggestion}</li>
                            ))}
                        </ul>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

function Stat({ label, value, hint }: { label: string; value: string; hint: string }) {
    return (
        <div className="rounded-lg border p-4">
            <p className="text-muted-foreground text-xs">{label}</p>
            <p className="text-xl font-semibold">{value}</p>
            <p className="text-muted-foreground text-xs">{hint}</p>
        </div>
    );
}
