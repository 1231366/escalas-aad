import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type Invitation } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { Check, Copy, MessageCircle, Plus, RotateCw, X } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Convites', href: '/admin/convites' }];

const statusBadge: Record<Invitation['status'], { label: string; variant: 'default' | 'secondary' | 'destructive' | 'outline' }> = {
    pending: { label: 'Pendente', variant: 'default' },
    accepted: { label: 'Aceite', variant: 'secondary' },
    expired: { label: 'Expirado', variant: 'outline' },
    revoked: { label: 'Revogado', variant: 'destructive' },
};

export default function InvitationsIndex({ invitations }: { invitations: Invitation[] }) {
    const [open, setOpen] = useState(false);
    const [copiedId, setCopiedId] = useState<number | null>(null);

    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        role: 'EMPLOYEE',
        regime: 'DIA',
        contract: 'H40',
        fixa_noite: false as boolean,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/admin/convites', {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                setOpen(false);
            },
        });
    };

    const copyLink = async (invitation: Invitation) => {
        if (!invitation.accept_url) return;
        await navigator.clipboard.writeText(invitation.accept_url);
        setCopiedId(invitation.id);
        setTimeout(() => setCopiedId(null), 2000);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Convites" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold">Convites</h1>
                        <p className="text-muted-foreground text-sm">Convida funcionárias por link — o perfil (regime, contrato) fica logo predefinido.</p>
                    </div>

                    <Dialog open={open} onOpenChange={setOpen}>
                        <DialogTrigger asChild>
                            <Button>
                                <Plus className="size-4" /> Novo convite
                            </Button>
                        </DialogTrigger>
                        <DialogContent className="sm:max-w-md">
                            <DialogHeader>
                                <DialogTitle>Novo convite</DialogTitle>
                                <DialogDescription>O link expira em 7 dias e só pode ser usado uma vez.</DialogDescription>
                            </DialogHeader>
                            <form onSubmit={submit} className="grid gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Nome</Label>
                                    <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} required autoFocus />
                                    <InputError message={errors.name} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="email">Email</Label>
                                    <Input id="email" type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} required />
                                    <InputError message={errors.email} />
                                </div>
                                <div className="grid grid-cols-2 gap-4">
                                    <div className="grid gap-2">
                                        <Label>Regime</Label>
                                        <Select value={data.regime} onValueChange={(v) => setData('regime', v)}>
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="DIA">Só dia (M/T)</SelectItem>
                                                <SelectItem value="NOITE">Só noite (N)</SelectItem>
                                                <SelectItem value="HIBRIDO">Híbrido</SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <InputError message={errors.regime} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label>Contrato</Label>
                                        <Select value={data.contract} onValueChange={(v) => setData('contract', v)}>
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="H40">40h/semana</SelectItem>
                                                <SelectItem value="H37_30">37h30/semana</SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <InputError message={errors.contract} />
                                    </div>
                                </div>
                                {data.regime !== 'DIA' && (
                                    <label className="flex items-center gap-2 text-sm">
                                        <Checkbox checked={data.fixa_noite} onCheckedChange={(v) => setData('fixa_noite', v === true)} />
                                        Fixa de noite (dedicada ao turno N)
                                    </label>
                                )}
                                <DialogFooter>
                                    <Button type="submit" disabled={processing}>
                                        Criar convite
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
                                <th className="p-3 font-medium">Nome</th>
                                <th className="p-3 font-medium">Email</th>
                                <th className="p-3 font-medium">Perfil predefinido</th>
                                <th className="p-3 font-medium">Estado</th>
                                <th className="p-3 text-right font-medium">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            {invitations.length === 0 && (
                                <tr>
                                    <td colSpan={5} className="text-muted-foreground p-8 text-center">
                                        Ainda não há convites. Cria o primeiro para começares a montar a equipa.
                                    </td>
                                </tr>
                            )}
                            {invitations.map((invitation) => (
                                <tr key={invitation.id} className="border-b last:border-0">
                                    <td className="p-3 font-medium">{invitation.name}</td>
                                    <td className="text-muted-foreground p-3">{invitation.email}</td>
                                    <td className="p-3">
                                        <span className="text-muted-foreground">
                                            {invitation.regime_label} · {invitation.contract_label}
                                            {invitation.fixa_noite && ' · fixa noite'}
                                        </span>
                                    </td>
                                    <td className="p-3">
                                        <Badge variant={statusBadge[invitation.status].variant}>{statusBadge[invitation.status].label}</Badge>
                                    </td>
                                    <td className="p-3">
                                        <div className="flex items-center justify-end gap-1">
                                            {invitation.whatsapp_url && (
                                                <Button variant="outline" size="sm" asChild title="Enviar por WhatsApp">
                                                    <a href={invitation.whatsapp_url} target="_blank" rel="noreferrer">
                                                        <MessageCircle className="size-4 text-green-600" /> WhatsApp
                                                    </a>
                                                </Button>
                                            )}
                                            {invitation.accept_url && (
                                                <Button variant="ghost" size="sm" onClick={() => copyLink(invitation)} title="Copiar link">
                                                    {copiedId === invitation.id ? <Check className="size-4 text-green-600" /> : <Copy className="size-4" />}
                                                </Button>
                                            )}
                                            {invitation.status !== 'accepted' && (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    title="Reenviar com novo link"
                                                    onClick={() => router.post(`/admin/convites/${invitation.id}/reenviar`, {}, { preserveScroll: true })}
                                                >
                                                    <RotateCw className="size-4" />
                                                </Button>
                                            )}
                                            {invitation.status === 'pending' && (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    title="Revogar"
                                                    onClick={() => router.post(`/admin/convites/${invitation.id}/revogar`, {}, { preserveScroll: true })}
                                                >
                                                    <X className="size-4 text-red-500" />
                                                </Button>
                                            )}
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
