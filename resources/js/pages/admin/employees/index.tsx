import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, ShieldOff, Trash2, UserCheck } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Funcionárias', href: '/admin/funcionarios' }];

interface EmployeeRow {
    id: number;
    name: string;
    regime: 'DIA' | 'NOITE' | 'HIBRIDO';
    regime_label: string;
    contract: 'H37_30' | 'H40';
    contract_label: string;
    fixa_noite: boolean;
    active: boolean;
    has_account: boolean;
    email: string | null;
}

type EmployeeFormValues = {
    name: string;
    regime: string;
    contract: string;
    fixa_noite: boolean;
    active: boolean;
};

const emptyForm: EmployeeFormValues = { name: '', regime: 'HIBRIDO', contract: 'H40', fixa_noite: false, active: true };

export default function EmployeesIndex({ employees }: { employees: EmployeeRow[] }) {
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<EmployeeRow | null>(null);

    const { data, setData, post, put, processing, errors, reset } = useForm<EmployeeFormValues>(emptyForm);

    const openCreate = () => {
        setEditing(null);
        reset();
        setData(emptyForm);
        setOpen(true);
    };

    const openEdit = (employee: EmployeeRow) => {
        setEditing(employee);
        setData({
            name: employee.name,
            regime: employee.regime,
            contract: employee.contract,
            fixa_noite: employee.fixa_noite,
            active: employee.active,
        });
        setOpen(true);
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editing) {
            put(`/admin/funcionarios/${editing.id}`, { preserveScroll: true, onSuccess: () => setOpen(false) });
        } else {
            post('/admin/funcionarios', { preserveScroll: true, onSuccess: () => setOpen(false) });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Funcionárias" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold">Funcionárias</h1>
                        <p className="text-muted-foreground text-sm">
                            Cria funcionárias sem conta para mockar a escala — entram na geração normalmente, mas nunca fazem login. Convida-as
                            mais tarde em <span className="font-medium">Convites</span> quando quiseres dar-lhes acesso.
                        </p>
                    </div>

                    <Dialog
                        open={open}
                        onOpenChange={(v) => {
                            setOpen(v);
                            if (!v) {
                                setEditing(null);
                                reset();
                            }
                        }}
                    >
                        <DialogTrigger asChild>
                            <Button onClick={openCreate}>
                                <Plus className="size-4" /> Nova funcionária
                            </Button>
                        </DialogTrigger>
                        <DialogContent className="sm:max-w-md">
                            <DialogHeader>
                                <DialogTitle>{editing ? 'Editar funcionária' : 'Nova funcionária sem acesso'}</DialogTitle>
                                <DialogDescription>
                                    {editing
                                        ? 'Atualiza o perfil de trabalho.'
                                        : 'Só cria o perfil de trabalho — sem email, sem password, sem login. Ideal para planear a escala antes de convidar.'}
                                </DialogDescription>
                            </DialogHeader>
                            <form onSubmit={submit} className="grid gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Nome</Label>
                                    <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} required autoFocus />
                                    <InputError message={errors.name} />
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
                                {editing && (
                                    <label className="flex items-center gap-2 text-sm">
                                        <Checkbox checked={data.active} onCheckedChange={(v) => setData('active', v === true)} />
                                        Ativa (entra na geração de escalas)
                                    </label>
                                )}
                                <DialogFooter>
                                    <Button type="submit" disabled={processing}>
                                        {editing ? 'Guardar' : 'Criar funcionária'}
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
                                <th className="p-3 font-medium">Perfil</th>
                                <th className="p-3 font-medium">Acesso</th>
                                <th className="p-3 font-medium">Estado</th>
                                <th className="p-3 text-right font-medium">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            {employees.length === 0 && (
                                <tr>
                                    <td colSpan={5} className="text-muted-foreground p-8 text-center">
                                        Sem funcionárias ainda. Cria uma sem acesso para começares a mockar a escala, ou convida alguém em
                                        Convites.
                                    </td>
                                </tr>
                            )}
                            {employees.map((employee) => (
                                <tr key={employee.id} className="border-b last:border-0">
                                    <td className="p-3 font-medium">{employee.name}</td>
                                    <td className="p-3">
                                        <span className="text-muted-foreground">
                                            {employee.regime_label} · {employee.contract_label}
                                            {employee.fixa_noite && ' · fixa noite'}
                                        </span>
                                    </td>
                                    <td className="p-3">
                                        {employee.has_account ? (
                                            <Badge variant="secondary" className="gap-1">
                                                <UserCheck className="size-3" /> {employee.email}
                                            </Badge>
                                        ) : (
                                            <Badge variant="outline" className="gap-1">
                                                <ShieldOff className="size-3" /> Sem acesso
                                            </Badge>
                                        )}
                                    </td>
                                    <td className="p-3">
                                        <Badge variant={employee.active ? 'default' : 'outline'}>{employee.active ? 'Ativa' : 'Inativa'}</Badge>
                                    </td>
                                    <td className="p-3">
                                        <div className="flex items-center justify-end gap-1">
                                            <Button variant="ghost" size="sm" title="Editar" onClick={() => openEdit(employee)}>
                                                <Pencil className="size-4" />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                title="Remover"
                                                onClick={() => {
                                                    const warning = employee.has_account
                                                        ? `Remover ${employee.name} definitivamente? Isto apaga também o acesso à conta (${employee.email}) e todo o histórico de turnos, férias e trocas. Não pode ser desfeito.`
                                                        : `Remover ${employee.name} definitivamente? Isto apaga o histórico de turnos, férias e trocas. Não pode ser desfeito.`;

                                                    if (confirm(warning)) {
                                                        router.delete(`/admin/funcionarios/${employee.id}`, { preserveScroll: true });
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
