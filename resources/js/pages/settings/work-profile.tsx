import { type BreadcrumbItem } from '@/types';
import { Transition } from '@headlessui/react';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

import HeadingSmall from '@/components/heading-small';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'O meu perfil de trabalho',
        href: '/settings/trabalho',
    },
];

interface WorkProfileEmployee {
    name: string;
    regime: 'DIA' | 'NOITE' | 'HIBRIDO';
    regime_label: string;
    contract: 'H37_30' | 'H40';
    contract_label: string;
    weekly_hours: number;
    fixa_noite: boolean;
    active: boolean;
}

interface NotificationPrefs {
    email?: {
        invitation_accepted?: boolean;
        schedule_published?: boolean;
        swap_request?: boolean;
        swap_decided?: boolean;
        vacation_requested?: boolean;
        vacation_decided?: boolean;
    };
}

interface NotificationOption {
    key: keyof NonNullable<NotificationPrefs['email']>;
    label: string;
}

const notificationOptions: NotificationOption[] = [
    { key: 'invitation_accepted', label: 'Convite aceite (admins)' },
    { key: 'schedule_published', label: 'Escala publicada' },
    { key: 'swap_request', label: 'Pedido de troca recebido' },
    { key: 'swap_decided', label: 'Troca aceite/recusada' },
    { key: 'vacation_requested', label: 'Pedido de férias recebido (admins)' },
    { key: 'vacation_decided', label: 'Férias decididas' },
];

export default function WorkProfile({
    employee,
    notification_prefs: notificationPrefs,
}: {
    employee: WorkProfileEmployee | null;
    notification_prefs: NotificationPrefs;
}) {
    const { data, setData, patch, processing, recentlySuccessful } = useForm({
        email: {
            invitation_accepted: notificationPrefs.email?.invitation_accepted ?? true,
            schedule_published: notificationPrefs.email?.schedule_published ?? true,
            swap_request: notificationPrefs.email?.swap_request ?? true,
            swap_decided: notificationPrefs.email?.swap_decided ?? true,
            vacation_requested: notificationPrefs.email?.vacation_requested ?? true,
            vacation_decided: notificationPrefs.email?.vacation_decided ?? true,
        },
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        patch(route('notification-prefs.update'), {
            preserveScroll: true,
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="O meu perfil de trabalho" />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall title="Perfil de trabalho" description="Dados do teu perfil de funcionária" />

                    {employee ? (
                        <div className="space-y-4 rounded-lg border p-4">
                            <dl className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <dt className="text-muted-foreground text-sm">Nome</dt>
                                    <dd className="text-sm font-medium">{employee.name}</dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground text-sm">Regime</dt>
                                    <dd className="text-sm font-medium">{employee.regime_label}</dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground text-sm">Contrato</dt>
                                    <dd className="text-sm font-medium">{employee.contract_label}</dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground text-sm">Horas/semana</dt>
                                    <dd className="text-sm font-medium">{employee.weekly_hours}h</dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground text-sm">Fixa noite</dt>
                                    <dd className="text-sm font-medium">
                                        <Badge variant={employee.fixa_noite ? 'default' : 'outline'}>{employee.fixa_noite ? 'Sim' : 'Não'}</Badge>
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground text-sm">Estado</dt>
                                    <dd className="text-sm font-medium">
                                        <Badge variant={employee.active ? 'default' : 'secondary'}>{employee.active ? 'Ativa' : 'Inativa'}</Badge>
                                    </dd>
                                </div>
                            </dl>

                            <p className="text-muted-foreground text-sm">Estes dados são geridos pela administração.</p>
                        </div>
                    ) : (
                        <div className="rounded-lg border border-dashed p-4">
                            <p className="text-muted-foreground text-sm">Sem perfil de funcionária associado.</p>
                        </div>
                    )}
                </div>

                <div className="space-y-6">
                    <HeadingSmall
                        title="Preferências de notificação"
                        description="Escolhe que emails queres receber. Todas estão ligadas por omissão."
                    />

                    <form onSubmit={submit} className="space-y-6">
                        <div className="space-y-4">
                            {notificationOptions.map((option) => (
                                <div key={option.key} className="flex items-center gap-3">
                                    <Checkbox
                                        id={option.key}
                                        checked={data.email[option.key]}
                                        onCheckedChange={(checked) =>
                                            setData('email', {
                                                ...data.email,
                                                [option.key]: checked === true,
                                            })
                                        }
                                    />
                                    <Label htmlFor={option.key}>{option.label}</Label>
                                </div>
                            ))}
                        </div>

                        <div className="flex items-center gap-4">
                            <Button disabled={processing}>Guardar preferências</Button>

                            <Transition
                                show={recentlySuccessful}
                                enter="transition ease-in-out"
                                enterFrom="opacity-0"
                                leave="transition ease-in-out"
                                leaveTo="opacity-0"
                            >
                                <p className="text-sm text-neutral-600">Guardado</p>
                            </Transition>
                        </div>
                    </form>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
