import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Check, X } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Trocas', href: '/trocas' }];

interface SwapItem {
    id: number;
    status: 'PENDING' | 'ACCEPTED' | 'DECLINED' | 'REJECTED' | 'APPLIED' | 'CANCELLED';
    requester: { id: number; name: string };
    target: { id: number; name: string };
    date: string;
    requester_shift: string | null;
    target_shift: string | null;
    admin_approval_required: boolean;
    created_at: string | null;
    decided_at: string | null;
    applied_at: string | null;
}

const statusBadge: Record<SwapItem['status'], { label: string; variant: 'default' | 'secondary' | 'destructive' | 'outline' }> = {
    PENDING: { label: 'Pendente', variant: 'default' },
    ACCEPTED: { label: 'Aceite — aguarda admin', variant: 'secondary' },
    DECLINED: { label: 'Recusada', variant: 'destructive' },
    REJECTED: { label: 'Rejeitada', variant: 'destructive' },
    APPLIED: { label: 'Aplicada', variant: 'secondary' },
    CANCELLED: { label: 'Cancelada', variant: 'outline' },
};

const formatDate = (dateStr: string) => new Date(dateStr).toLocaleDateString('pt-PT', { weekday: 'short', day: 'numeric', month: 'short' });

function SwapCard({ swap, mine }: { swap: SwapItem; mine: 'sent' | 'received' }) {
    const accept = () => router.post(`/trocas/${swap.id}/aceitar`, {}, { preserveScroll: true });
    const decline = () => router.post(`/trocas/${swap.id}/recusar`, {}, { preserveScroll: true });
    const cancel = () => router.post(`/trocas/${swap.id}/cancelar`, {}, { preserveScroll: true });

    return (
        <Card>
            <CardHeader className="flex flex-row items-start justify-between space-y-0 p-4">
                <div>
                    <CardTitle className="text-base font-medium capitalize">{formatDate(swap.date)}</CardTitle>
                    <p className="text-muted-foreground text-sm">
                        {swap.requester.name} ({swap.requester_shift ?? 'Folga'}) ⇄ {swap.target.name} ({swap.target_shift ?? 'Folga'})
                    </p>
                </div>
                <Badge variant={statusBadge[swap.status].variant}>{statusBadge[swap.status].label}</Badge>
            </CardHeader>
            {swap.status === 'PENDING' && (
                <CardContent className="flex gap-2 p-4 pt-0">
                    {mine === 'received' ? (
                        <>
                            <Button size="sm" onClick={accept}>
                                <Check className="size-4" /> Aceitar
                            </Button>
                            <Button size="sm" variant="outline" onClick={decline}>
                                <X className="size-4" /> Recusar
                            </Button>
                        </>
                    ) : (
                        <Button size="sm" variant="outline" onClick={cancel}>
                            <X className="size-4" /> Cancelar pedido
                        </Button>
                    )}
                </CardContent>
            )}
        </Card>
    );
}

export default function SwapsIndex({ sent, received }: { sent: SwapItem[]; received: SwapItem[] }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Trocas" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div>
                    <h1 className="text-xl font-semibold">As minhas trocas</h1>
                    <p className="text-muted-foreground text-sm">
                        Para pedir uma troca, vai à tua escala e passa o rato sobre um turno futuro teu.
                    </p>
                </div>

                <section className="space-y-3">
                    <h2 className="text-sm font-medium">Pedidos recebidos</h2>
                    {received.length === 0 ? (
                        <p className="text-muted-foreground text-sm">Ninguém te pediu para trocar, por agora.</p>
                    ) : (
                        received.map((swap) => <SwapCard key={swap.id} swap={swap} mine="received" />)
                    )}
                </section>

                <section className="space-y-3">
                    <h2 className="text-sm font-medium">Pedidos enviados</h2>
                    {sent.length === 0 ? (
                        <p className="text-muted-foreground text-sm">Ainda não pediste nenhuma troca.</p>
                    ) : (
                        sent.map((swap) => <SwapCard key={swap.id} swap={swap} mine="sent" />)
                    )}
                </section>
            </div>
        </AppLayout>
    );
}
