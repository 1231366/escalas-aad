import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type VacationRequestItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { Plus, X } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Férias', href: '/ferias' }];

const statusBadge: Record<VacationRequestItem['status'], { label: string; variant: 'default' | 'secondary' | 'destructive' | 'outline' }> = {
    PENDING: { label: 'Pendente', variant: 'default' },
    APPROVED: { label: 'Aprovado', variant: 'secondary' },
    DECLINED: { label: 'Recusado', variant: 'destructive' },
    CANCELLED: { label: 'Cancelado', variant: 'outline' },
};

const formatDate = (dateStr: string) => new Date(dateStr).toLocaleDateString('pt-PT');

export default function VacationsIndex({ vacations }: { vacations: VacationRequestItem[] }) {
    const [open, setOpen] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        start_date: '',
        end_date: '',
        note: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/ferias', {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                setOpen(false);
            },
        });
    };

    const cancel = (vacation: VacationRequestItem) => {
        router.post(`/ferias/${vacation.id}/cancelar`, {}, { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Férias" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold">Férias</h1>
                        <p className="text-muted-foreground text-sm">Pede férias e acompanha o estado dos teus pedidos.</p>
                    </div>

                    <Dialog open={open} onOpenChange={setOpen}>
                        <DialogTrigger asChild>
                            <Button>
                                <Plus className="size-4" /> Pedir férias
                            </Button>
                        </DialogTrigger>
                        <DialogContent className="sm:max-w-md">
                            <DialogHeader>
                                <DialogTitle>Novo pedido de férias</DialogTitle>
                                <DialogDescription>O admin vê o impacto na cobertura antes de decidir.</DialogDescription>
                            </DialogHeader>
                            <form onSubmit={submit} className="grid gap-4">
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
                                    <Label htmlFor="note">Nota (opcional)</Label>
                                    <Input id="note" value={data.note} onChange={(e) => setData('note', e.target.value)} />
                                    <InputError message={errors.note} />
                                </div>
                                <DialogFooter>
                                    <Button type="submit" disabled={processing}>
                                        Enviar pedido
                                    </Button>
                                </DialogFooter>
                            </form>
                        </DialogContent>
                    </Dialog>
                </div>

                {vacations.length === 0 && (
                    <div className="text-muted-foreground rounded-xl border border-dashed p-8 text-center text-sm">
                        Ainda não pediste férias. Usa o botão acima para criar o primeiro pedido.
                    </div>
                )}

                <div className="grid gap-3">
                    {vacations.map((vacation) => (
                        <Card key={vacation.id}>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 p-4">
                                <CardTitle className="text-base font-medium">
                                    {formatDate(vacation.start_date)} — {formatDate(vacation.end_date)}
                                </CardTitle>
                                <div className="flex items-center gap-2">
                                    <Badge variant={statusBadge[vacation.status].variant}>{statusBadge[vacation.status].label}</Badge>
                                    {vacation.status === 'PENDING' && (
                                        <Button variant="ghost" size="sm" title="Cancelar pedido" onClick={() => cancel(vacation)}>
                                            <X className="size-4 text-red-500" />
                                        </Button>
                                    )}
                                </div>
                            </CardHeader>
                            {vacation.note && (
                                <CardContent className="text-muted-foreground p-4 pt-0 text-sm">{vacation.note}</CardContent>
                            )}
                        </Card>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
