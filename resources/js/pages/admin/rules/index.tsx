import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type CoverageEntry, type RuleConfigs, type ShiftType } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { Save } from 'lucide-react';
import { FormEvent } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Regras', href: '/admin/regras' }];

const weekdayLabels = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'];

interface Props {
    shift_types: ShiftType[];
    coverage: CoverageEntry[];
    rule_configs: RuleConfigs;
}

export default function RulesIndex({ shift_types, coverage, rule_configs }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Regras" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div>
                    <h1 className="text-xl font-semibold">Regras</h1>
                    <p className="text-muted-foreground text-sm">
                        Configura os turnos, a cobertura exigida por dia da semana e os parâmetros legais/operacionais usados pelo gerador de
                        escalas.
                    </p>
                </div>

                <ShiftTypesSection shiftTypes={shift_types} />
                <CoverageSection shiftTypes={shift_types} coverage={coverage} />
                <ParametersSection ruleConfigs={rule_configs} />
            </div>
        </AppLayout>
    );
}

function ShiftTypesSection({ shiftTypes }: { shiftTypes: ShiftType[] }) {
    return (
        <section className="flex flex-col gap-3">
            <h2 className="text-lg font-medium">Turnos</h2>
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {shiftTypes.map((shiftType) => (
                    <ShiftTypeCard key={shiftType.id} shiftType={shiftType} />
                ))}
            </div>
        </section>
    );
}

function ShiftTypeCard({ shiftType }: { shiftType: ShiftType }) {
    const { data, setData, put, processing, errors, recentlySuccessful } = useForm({
        starts_at: shiftType.starts_at,
        ends_at: shiftType.ends_at,
        color: shiftType.color,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        put(`/admin/regras/turnos/${shiftType.id}`, { preserveScroll: true });
    };

    return (
        <Card>
            <CardHeader className="flex-row items-center justify-between space-y-0 pb-2">
                <div>
                    <CardTitle className="text-base">
                        {shiftType.code} — {shiftType.name}
                    </CardTitle>
                    <CardDescription>{shiftType.hours}h por turno</CardDescription>
                </div>
                <span className="size-6 rounded-full border" style={{ backgroundColor: data.color }} aria-hidden />
            </CardHeader>
            <form onSubmit={submit}>
                <CardContent className="grid gap-3">
                    <div className="grid grid-cols-2 gap-3">
                        <div className="grid gap-1.5">
                            <Label htmlFor={`starts-${shiftType.id}`}>Início</Label>
                            <Input
                                id={`starts-${shiftType.id}`}
                                type="time"
                                value={data.starts_at}
                                onChange={(e) => setData('starts_at', e.target.value)}
                            />
                            <InputError message={errors.starts_at} />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor={`ends-${shiftType.id}`}>Fim</Label>
                            <Input id={`ends-${shiftType.id}`} type="time" value={data.ends_at} onChange={(e) => setData('ends_at', e.target.value)} />
                            <InputError message={errors.ends_at} />
                        </div>
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor={`color-${shiftType.id}`}>Cor</Label>
                        <Input
                            id={`color-${shiftType.id}`}
                            type="color"
                            className="h-10 w-full p-1"
                            value={data.color}
                            onChange={(e) => setData('color', e.target.value)}
                        />
                        <InputError message={errors.color} />
                    </div>
                </CardContent>
                <CardFooter className="justify-between">
                    {recentlySuccessful && <span className="text-muted-foreground text-xs">Guardado.</span>}
                    <Button type="submit" size="sm" disabled={processing} className="ml-auto">
                        <Save className="size-4" /> Guardar
                    </Button>
                </CardFooter>
            </form>
        </Card>
    );
}

function CoverageSection({ shiftTypes, coverage }: { shiftTypes: ShiftType[]; coverage: CoverageEntry[] }) {
    const { data, setData, put, processing, errors, recentlySuccessful } = useForm<{ coverage: CoverageEntry[] }>({ coverage });

    const cellValue = (shiftTypeId: number, weekday: number) =>
        data.coverage.find((row) => row.shift_type_id === shiftTypeId && row.weekday === weekday)?.required ?? 0;

    const cellError = (index: number) => errors[`coverage.${index}.required` as keyof typeof errors];

    const updateCell = (shiftTypeId: number, weekday: number, value: number) => {
        setData(
            'coverage',
            data.coverage.map((row) => (row.shift_type_id === shiftTypeId && row.weekday === weekday ? { ...row, required: value } : row)),
        );
    };

    const totalForWeekday = (weekday: number) => data.coverage.filter((row) => row.weekday === weekday).reduce((sum, row) => sum + row.required, 0);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        put('/admin/regras/cobertura', { preserveScroll: true });
    };

    return (
        <section className="flex flex-col gap-3">
            <h2 className="text-lg font-medium">Cobertura por dia da semana</h2>
            <p className="text-muted-foreground text-sm">Número de AAD exigidas em cada turno, por dia da semana (ex.: 4M/3T/2N).</p>
            <form onSubmit={submit} className="flex flex-col gap-3">
                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full min-w-[560px] text-sm">
                        <thead>
                            <tr className="bg-muted/50 text-muted-foreground border-b text-left">
                                <th className="p-3 font-medium">Turno</th>
                                {weekdayLabels.map((label) => (
                                    <th key={label} className="p-3 text-center font-medium">
                                        {label}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {shiftTypes.map((shiftType) => (
                                <tr key={shiftType.id} className="border-b last:border-0">
                                    <td className="p-3 font-medium">
                                        <span className="inline-flex items-center gap-2">
                                            <span className="size-3 rounded-full" style={{ backgroundColor: shiftType.color }} aria-hidden />
                                            {shiftType.code}
                                        </span>
                                    </td>
                                    {Array.from({ length: 7 }, (_, weekday) => weekday).map((weekday) => (
                                        <td key={weekday} className="p-2 text-center">
                                            <Input
                                                type="number"
                                                min={0}
                                                max={20}
                                                className="mx-auto h-9 w-16 text-center"
                                                value={cellValue(shiftType.id, weekday)}
                                                onChange={(e) => updateCell(shiftType.id, weekday, Number(e.target.value))}
                                            />
                                        </td>
                                    ))}
                                </tr>
                            ))}
                            <tr className="bg-muted/30">
                                <td className="p-3 font-medium">Total</td>
                                {Array.from({ length: 7 }, (_, weekday) => weekday).map((weekday) => (
                                    <td key={weekday} className="text-muted-foreground p-3 text-center font-medium">
                                        {totalForWeekday(weekday)}
                                    </td>
                                ))}
                            </tr>
                        </tbody>
                    </table>
                </div>
                {data.coverage.map((_, index) => (
                    <InputError key={index} message={cellError(index)} />
                ))}
                <div className="flex items-center gap-3">
                    <Button type="submit" disabled={processing}>
                        <Save className="size-4" /> Guardar cobertura
                    </Button>
                    {recentlySuccessful && <span className="text-muted-foreground text-sm">Cobertura guardada.</span>}
                </div>
            </form>
        </section>
    );
}

function ParametersSection({ ruleConfigs }: { ruleConfigs: RuleConfigs }) {
    const { data, setData, put, processing, errors, recentlySuccessful } = useForm({
        hour_bank_weekly_tolerance: ruleConfigs.hour_bank_weekly_tolerance,
        max_consecutive_work_days: ruleConfigs.max_consecutive_work_days,
        ff_window_weeks: ruleConfigs.ff_window_weeks,
        ff_monthly: ruleConfigs.ff_monthly,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        put('/admin/regras/parametros', { preserveScroll: true });
    };

    return (
        <section className="flex flex-col gap-3">
            <h2 className="text-lg font-medium">Parâmetros das regras</h2>
            <Card>
                <form onSubmit={submit}>
                    <CardContent className="grid gap-6 pt-6 sm:grid-cols-2">
                        <div className="grid gap-1.5">
                            <Label htmlFor="hour_bank_weekly_tolerance">Banco de horas (h/semana)</Label>
                            <Input
                                id="hour_bank_weekly_tolerance"
                                type="number"
                                min={0}
                                max={16}
                                step={0.5}
                                value={data.hour_bank_weekly_tolerance}
                                onChange={(e) => setData('hour_bank_weekly_tolerance', Number(e.target.value))}
                            />
                            <p className="text-muted-foreground text-xs">
                                Tolerância acima do contrato que absorve o défice estrutural de cobertura (ADR-0003).
                            </p>
                            <InputError message={errors.hour_bank_weekly_tolerance} />
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="max_consecutive_work_days">Máx. dias consecutivos</Label>
                            <Input
                                id="max_consecutive_work_days"
                                type="number"
                                min={1}
                                max={6}
                                value={data.max_consecutive_work_days}
                                onChange={(e) => setData('max_consecutive_work_days', Number(e.target.value))}
                            />
                            <p className="text-muted-foreground text-xs">Número máximo de dias de trabalho seguidos (limite legal: 6).</p>
                            <InputError message={errors.max_consecutive_work_days} />
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="ff_window_weeks">Janela de folgas seguidas (semanas)</Label>
                            <Input
                                id="ff_window_weeks"
                                type="number"
                                min={1}
                                max={12}
                                value={data.ff_window_weeks}
                                onChange={(e) => setData('ff_window_weeks', Number(e.target.value))}
                            />
                            <p className="text-muted-foreground text-xs">
                                Cada funcionária deve ter 2 folgas seguidas ("FF") pelo menos uma vez a cada N semanas.
                            </p>
                            <InputError message={errors.ff_window_weeks} />
                        </div>

                        <div className="grid gap-1.5">
                            <Label>Preferir FF 1×/mês</Label>
                            <label className="flex items-center gap-2 text-sm">
                                <Checkbox checked={data.ff_monthly} onCheckedChange={(v) => setData('ff_monthly', v === true)} />
                                Dar preferência a uma dupla folga por mês
                            </label>
                            <p className="text-muted-foreground text-xs">
                                Preferência (não obrigação) — o obrigatório é a janela de semanas acima. O gerador também equilibra o nº de FF entre
                                funcionárias.
                            </p>
                            <InputError message={errors.ff_monthly} />
                        </div>
                    </CardContent>
                    <CardFooter className="justify-between">
                        {recentlySuccessful && <span className="text-muted-foreground text-sm">Parâmetros guardados.</span>}
                        <Button type="submit" disabled={processing} className="ml-auto">
                            <Save className="size-4" /> Guardar parâmetros
                        </Button>
                    </CardFooter>
                </form>
            </Card>
        </section>
    );
}
