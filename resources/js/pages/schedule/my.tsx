import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type ScheduleDate, type ScheduleEmployeeRow, type ScheduleMeta, type ShiftType } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeftRight } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'A minha escala', href: '/escala' }];

interface Props {
    schedule: ScheduleMeta | null;
    shift_types: ShiftType[];
    dates: ScheduleDate[];
    employees: ScheduleEmployeeRow[];
    my_employee_id: number | null;
}

const monthLabel = (dateStr: string) => new Date(dateStr).toLocaleDateString('pt-PT', { month: 'long', year: 'numeric' });

const today = () => new Date().toISOString().slice(0, 10);

export default function MySchedule({ schedule, shift_types, dates, employees }: Props) {
    const shiftByCode = Object.fromEntries(shift_types.map((s) => [s.code, s]));
    const todayStr = today();

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="A minha escala" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div>
                    <h1 className="text-xl font-semibold">A minha escala</h1>
                    {schedule ? (
                        <p className="text-muted-foreground text-sm capitalize">
                            {monthLabel(schedule.period_start)}
                            {schedule.published_at && ` · publicada em ${new Date(schedule.published_at).toLocaleDateString('pt-PT')}`}
                        </p>
                    ) : (
                        <p className="text-muted-foreground text-sm">Ainda não há uma escala publicada.</p>
                    )}
                </div>

                {!schedule && (
                    <div className="text-muted-foreground rounded-xl border p-8 text-center text-sm">
                        Quando o admin publicar a escala do mês, ela aparece aqui.
                    </div>
                )}

                {schedule && (
                    <div className="overflow-x-auto rounded-xl border">
                        <table className="w-full border-collapse text-sm">
                            <thead>
                                <tr>
                                    <th className="bg-background sticky top-0 left-0 z-20 border-r border-b p-2 text-left font-medium">
                                        Funcionária
                                    </th>
                                    {dates.map((date) => (
                                        <th
                                            key={date.date}
                                            className={`sticky top-0 z-10 min-w-10 border-b p-1 text-center text-xs font-medium ${
                                                date.is_current_week ? 'bg-primary/10' : date.is_weekend ? 'bg-muted' : 'bg-background'
                                            }`}
                                        >
                                            <div>{date.weekday_label}</div>
                                            <div>{date.day}</div>
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {employees.map((employee) => (
                                    <tr key={employee.employee_id} className={`border-b last:border-0 ${employee.is_self ? '' : 'opacity-60'}`}>
                                        <td
                                            className={`sticky left-0 z-10 border-r p-2 font-medium ${
                                                employee.is_self ? 'bg-primary/5' : 'bg-background'
                                            }`}
                                        >
                                            {employee.name}
                                            {employee.is_self && (
                                                <Badge variant="secondary" className="ml-2 align-middle text-[10px]">
                                                    Eu
                                                </Badge>
                                            )}
                                        </td>
                                        {employee.cells.map((cell) => {
                                            const shiftType = cell.shift_code ? shiftByCode[cell.shift_code] : null;
                                            const canSwap = employee.is_self && shiftType && cell.assignment_id && cell.date >= todayStr;

                                            return (
                                                <td key={cell.date} className="group relative p-1 text-center">
                                                    {shiftType ? (
                                                        <span
                                                            className={`block rounded py-1 text-xs font-semibold text-white ${
                                                                employee.is_self ? 'ring-primary ring-2 ring-offset-1' : ''
                                                            }`}
                                                            style={{ backgroundColor: shiftType.color }}
                                                        >
                                                            {shiftType.code}
                                                        </span>
                                                    ) : (
                                                        <span className="text-muted-foreground bg-muted/40 block rounded py-1 text-xs">
                                                            {cell.is_day_off ? 'F' : ''}
                                                        </span>
                                                    )}
                                                    {canSwap && (
                                                        <Link
                                                            href={route('swaps.create', cell.assignment_id as number)}
                                                            title="Trocar este turno"
                                                            className="bg-background text-foreground absolute inset-0 m-auto hidden size-5 items-center justify-center rounded-full border shadow group-hover:flex"
                                                        >
                                                            <ArrowLeftRight className="size-3" />
                                                        </Link>
                                                    )}
                                                </td>
                                            );
                                        })}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
