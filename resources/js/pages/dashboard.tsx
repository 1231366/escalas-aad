import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { PlaceholderPattern } from '@/components/ui/placeholder-pattern';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type Viability } from '@/types';
import { Head } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, Moon, XCircle } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

interface Props {
    viability: Viability | null;
}

export default function Dashboard({ viability }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                {viability ? (
                    <ViabilityCard viability={viability} />
                ) : (
                    <>
                        <div className="grid auto-rows-min gap-4 md:grid-cols-3">
                            <PlaceholderBox />
                            <PlaceholderBox />
                            <PlaceholderBox />
                        </div>
                        <div className="border-sidebar-border/70 dark:border-sidebar-border relative min-h-[100vh] flex-1 rounded-xl border md:min-h-min">
                            <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
                        </div>
                    </>
                )}
            </div>
        </AppLayout>
    );
}

function PlaceholderBox() {
    return (
        <div className="border-sidebar-border/70 dark:border-sidebar-border relative aspect-video overflow-hidden rounded-xl border">
            <PlaceholderPattern className="absolute inset-0 size-full stroke-neutral-900/20 dark:stroke-neutral-100/20" />
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
