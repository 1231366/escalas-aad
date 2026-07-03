import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type AdminVacationRequestItem, type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Check, X } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Férias', href: '/admin/ferias' }];

const statusBadge: Record<
    AdminVacationRequestItem['status'],
    { label: string; variant: 'default' | 'secondary' | 'destructive' | 'outline' }
> = {
    PENDING: { label: 'Pendente', variant: 'default' },
    APPROVED: { label: 'Aprovado', variant: 'secondary' },
    DECLINED: { label: 'Recusado', variant: 'destructive' },
    CANCELLED: { label: 'Cancelado', variant: 'outline' },
};

const formatDate = (dateStr: string) => new Date(dateStr).toLocaleDateString('pt-PT');

function ImpactBadge({ impact }: { impact: AdminVacationRequestItem['impact'] }) {
    if (!impact) {
        return <Badge variant="outline">Impacto desconhecido</Badge>;
    }

    if (impact.no_schedule) {
        return (
            <Badge variant="outline" className="border-transparent bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300">
                Sem escala publicada no período
            </Badge>
        );
    }

    if (impact.ok && impact.issues.length === 0) {
        return (
            <Badge variant="outline" className="border-transparent bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-300">
                Sem impacto na cobertura
            </Badge>
        );
    }

    return (
        <div className="space-y-1">
            <Badge variant="outline" className="border-transparent bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300">
                Impacto na cobertura
            </Badge>
            <ul className="text-muted-foreground list-disc space-y-0.5 pl-4 text-xs">
                {impact.issues.map((issue, index) => (
                    <li key={index}>
                        {issue.date ? `${formatDate(issue.date)} — ` : ''}
                        {issue.message} ({issue.rule})
                    </li>
                ))}
            </ul>
        </div>
    );
}

export default function AdminVacationsIndex({ vacations }: { vacations: AdminVacationRequestItem[] }) {
    const approve = (vacation: AdminVacationRequestItem) => {
        router.post(`/admin/ferias/${vacation.id}/aprovar`, {}, { preserveScroll: true });
    };

    const decline = (vacation: AdminVacationRequestItem) => {
        router.post(`/admin/ferias/${vacation.id}/recusar`, {}, { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Férias" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div>
                    <h1 className="text-xl font-semibold">Pedidos de férias</h1>
                    <p className="text-muted-foreground text-sm">
                        O impacto na cobertura é calculado pelo solver assim que o pedido é criado — usa-o para decidir.
                    </p>
                </div>

                {vacations.length === 0 && (
                    <div className="text-muted-foreground rounded-xl border border-dashed p-8 text-center text-sm">
                        Ainda não há pedidos de férias.
                    </div>
                )}

                <div className="grid gap-3">
                    {vacations.map((vacation) => (
                        <Card key={vacation.id}>
                            <CardHeader className="flex flex-row items-start justify-between space-y-0 p-4">
                                <div>
                                    <CardTitle className="text-base font-medium">{vacation.employee_name}</CardTitle>
                                    <p className="text-muted-foreground text-sm">
                                        {formatDate(vacation.start_date)} — {formatDate(vacation.end_date)}
                                    </p>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Badge variant={statusBadge[vacation.status].variant}>{statusBadge[vacation.status].label}</Badge>
                                    {vacation.status === 'PENDING' && (
                                        <div className="flex items-center gap-1">
                                            <Button variant="ghost" size="sm" title="Aprovar" onClick={() => approve(vacation)}>
                                                <Check className="size-4 text-green-600" />
                                            </Button>
                                            <Button variant="ghost" size="sm" title="Recusar" onClick={() => decline(vacation)}>
                                                <X className="size-4 text-red-500" />
                                            </Button>
                                        </div>
                                    )}
                                </div>
                            </CardHeader>
                            <CardContent className="space-y-2 p-4 pt-0">
                                <ImpactBadge impact={vacation.impact} />
                                {vacation.note && <p className="text-muted-foreground text-sm">Nota: {vacation.note}</p>}
                                {vacation.decided_by_name && (
                                    <p className="text-muted-foreground text-xs">
                                        Decidido por {vacation.decided_by_name}
                                        {vacation.decided_at && ` em ${new Date(vacation.decided_at).toLocaleString('pt-PT')}`}
                                    </p>
                                )}
                            </CardContent>
                        </Card>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
