import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type ScheduleSummary } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { FormEvent, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Escalas', href: '/admin/escalas' }];

const monthNames = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];

const statusBadge: Record<ScheduleSummary['status'], { label: string; variant: 'default' | 'secondary' | 'destructive' | 'outline' }> = {
    DRAFT: { label: 'Rascunho', variant: 'default' },
    PUBLISHED: { label: 'Publicada', variant: 'secondary' },
    ARCHIVED: { label: 'Arquivada', variant: 'outline' },
};

export default function SchedulesIndex({ schedules }: { schedules: ScheduleSummary[] }) {
    const [open, setOpen] = useState(false);
    const now = new Date();
    const nextMonth = new Date(now.getFullYear(), now.getMonth() + 1, 1);

    const { data, setData, post, processing, errors, reset } = useForm({
        year: nextMonth.getFullYear(),
        month: nextMonth.getMonth() + 1,
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post('/admin/escalas', {
            onSuccess: () => {
                reset();
                setOpen(false);
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Escalas" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold">Escalas</h1>
                        <p className="text-muted-foreground text-sm">Gera a escala mensal com o solver, revê a grelha e publica para a equipa.</p>
                    </div>

                    <Dialog open={open} onOpenChange={setOpen}>
                        <DialogTrigger asChild>
                            <Button>
                                <Plus className="size-4" /> Nova escala
                            </Button>
                        </DialogTrigger>
                        <DialogContent className="sm:max-w-sm">
                            <DialogHeader>
                                <DialogTitle>Nova escala</DialogTitle>
                                <DialogDescription>
                                    Escolhe o mês. A escala fica em rascunho e o solver gera-a automaticamente.
                                </DialogDescription>
                            </DialogHeader>
                            <form onSubmit={submit} className="grid gap-4">
                                <div className="grid grid-cols-2 gap-4">
                                    <div className="grid gap-2">
                                        <Label>Mês</Label>
                                        <Select value={String(data.month)} onValueChange={(v) => setData('month', Number(v))}>
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {monthNames.map((name, i) => (
                                                    <SelectItem key={name} value={String(i + 1)}>
                                                        {name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <InputError message={errors.month} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="year">Ano</Label>
                                        <Input
                                            id="year"
                                            type="number"
                                            value={data.year}
                                            onChange={(e) => setData('year', Number(e.target.value))}
                                        />
                                        <InputError message={errors.year} />
                                    </div>
                                </div>
                                <DialogFooter>
                                    <Button type="submit" disabled={processing}>
                                        Criar e gerar
                                    </Button>
                                </DialogFooter>
                            </form>
                        </DialogContent>
                    </Dialog>
                </div>

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="bg-muted/50 text-muted-foreground border-b text-left">
                                <th className="p-3 font-medium">Período</th>
                                <th className="p-3 font-medium">Estado</th>
                                <th className="p-3 font-medium">Gerada em</th>
                                <th className="p-3 font-medium">Publicada em</th>
                                <th className="p-3 text-right font-medium">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            {schedules.length === 0 && (
                                <tr>
                                    <td colSpan={5} className="text-muted-foreground p-8 text-center">
                                        Ainda não há escalas. Cria a primeira para o próximo mês.
                                    </td>
                                </tr>
                            )}
                            {schedules.map((schedule) => (
                                <tr key={schedule.id} className="border-b last:border-0">
                                    <td className="p-3 font-medium capitalize">{schedule.label}</td>
                                    <td className="p-3">
                                        <Badge variant={statusBadge[schedule.status].variant}>{statusBadge[schedule.status].label}</Badge>
                                    </td>
                                    <td className="text-muted-foreground p-3">
                                        {schedule.generated_at ? new Date(schedule.generated_at).toLocaleString('pt-PT') : '—'}
                                    </td>
                                    <td className="text-muted-foreground p-3">
                                        {schedule.published_at ? new Date(schedule.published_at).toLocaleString('pt-PT') : '—'}
                                    </td>
                                    <td className="p-3 text-right">
                                        <div className="flex items-center justify-end gap-1">
                                            <Button variant="outline" size="sm" asChild>
                                                <Link href={`/admin/escalas/${schedule.id}`}>Ver escala</Link>
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                title="Apagar"
                                                onClick={() => {
                                                    if (
                                                        confirm(
                                                            `Apagar a escala de ${schedule.label} definitivamente? Isto remove todos os turnos${schedule.status === 'PUBLISHED' ? ' — a equipa perde o acesso a esta escala' : ''}. Não pode ser desfeito.`,
                                                        )
                                                    ) {
                                                        router.delete(`/admin/escalas/${schedule.id}`);
                                                    }
                                                }}
                                            >
                                                <Trash2 className="size-4 text-red-500" />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}
