import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { ArrowLeftRight } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Trocas', href: '/trocas' },
    { title: 'Nova troca', href: '#' },
];

interface Candidate {
    employee_id: number;
    name: string;
    shift: string | null;
}

interface Props {
    assignment: { id: number; date: string; shift_code: string | null };
    candidates: Candidate[];
}

const dateLabel = (dateStr: string) =>
    new Date(dateStr).toLocaleDateString('pt-PT', { weekday: 'long', day: 'numeric', month: 'long' });

export default function SwapCreate({ assignment, candidates }: Props) {
    const request = (targetEmployeeId: number) => {
        router.post('/trocas', {
            requester_assignment_id: assignment.id,
            target_employee_id: targetEmployeeId,
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nova troca" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div>
                    <h1 className="text-xl font-semibold">Trocar o meu turno</h1>
                    <p className="text-muted-foreground text-sm capitalize">
                        {dateLabel(assignment.date)} · turno {assignment.shift_code}
                    </p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Com quem posso trocar</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {candidates.length === 0 ? (
                            <p className="text-muted-foreground text-sm">
                                Nenhuma colega pode trocar contigo nesse dia sem violar alguma regra (descanso, regime, cobertura). Tenta noutro
                                dia.
                            </p>
                        ) : (
                            <ul className="divide-y">
                                {candidates.map((candidate) => (
                                    <li key={candidate.employee_id} className="flex items-center justify-between py-3">
                                        <div className="flex items-center gap-2">
                                            <span className="font-medium">{candidate.name}</span>
                                            <Badge variant="outline">{candidate.shift ?? 'Folga'}</Badge>
                                        </div>
                                        <Button size="sm" onClick={() => request(candidate.employee_id)}>
                                            <ArrowLeftRight className="size-4" /> Pedir troca
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
