import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Check, X } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Trocas', href: '/admin/trocas' }];

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
    accepted_at: string | null;
    decided_at: string | null;
    applied_at: string | null;
}

const statusBadge: Record<SwapItem['status'], { label: string; variant: 'default' | 'secondary' | 'destructive' | 'outline' }> = {
    PENDING: { label: 'Pendente (colega)', variant: 'default' },
    ACCEPTED: { label: 'Aguarda aprovação', variant: 'secondary' },
    DECLINED: { label: 'Recusada pela colega', variant: 'destructive' },
    REJECTED: { label: 'Rejeitada', variant: 'destructive' },
    APPLIED: { label: 'Aplicada', variant: 'secondary' },
    CANCELLED: { label: 'Cancelada', variant: 'outline' },
};

const formatDate = (dateStr: string) => new Date(dateStr).toLocaleDateString('pt-PT', { weekday: 'short', day: 'numeric', month: 'short' });

export default function AdminSwapsIndex({ swaps }: { swaps: SwapItem[] }) {
    const approve = (swap: SwapItem) => router.post(`/admin/trocas/${swap.id}/aprovar`, {}, { preserveScroll: true });
    const reject = (swap: SwapItem) => router.post(`/admin/trocas/${swap.id}/rejeitar`, {}, { preserveScroll: true });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Trocas" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div>
                    <h1 className="text-xl font-semibold">Trocas entre funcionárias</h1>
                    <p className="text-muted-foreground text-sm">
                        Só precisas de agir nas que aguardam aprovação — as restantes resolvem-se entre as próprias colegas.
                    </p>
                </div>

                {swaps.length === 0 && (
                    <div className="text-muted-foreground rounded-xl border border-dashed p-8 text-center text-sm">Ainda não há trocas.</div>
                )}

                <div className="grid gap-3">
                    {swaps.map((swap) => (
                        <Card key={swap.id}>
                            <CardHeader className="flex flex-row items-start justify-between space-y-0 p-4">
                                <div>
                                    <CardTitle className="text-base font-medium capitalize">{formatDate(swap.date)}</CardTitle>
                                    <p className="text-muted-foreground text-sm">
                                        {swap.requester.name} ({swap.requester_shift ?? 'Folga'}) ⇄ {swap.target.name} (
                                        {swap.target_shift ?? 'Folga'})
                                    </p>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Badge variant={statusBadge[swap.status].variant}>{statusBadge[swap.status].label}</Badge>
                                    {swap.status === 'ACCEPTED' && (
                                        <div className="flex items-center gap-1">
                                            <Button variant="ghost" size="sm" title="Aprovar" onClick={() => approve(swap)}>
                                                <Check className="size-4 text-green-600" />
                                            </Button>
                                            <Button variant="ghost" size="sm" title="Rejeitar" onClick={() => reject(swap)}>
                                                <X className="size-4 text-red-500" />
                                            </Button>
                                        </div>
                                    )}
                                </div>
                            </CardHeader>
                            {!swap.admin_approval_required && (
                                <CardContent className="p-4 pt-0">
                                    <p className="text-muted-foreground text-xs">Esta organização não exige aprovação — resolve-se entre elas.</p>
                                </CardContent>
                            )}
                        </Card>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
