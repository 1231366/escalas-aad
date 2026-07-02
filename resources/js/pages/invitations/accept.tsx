import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';
import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';

interface AcceptProps {
    invitation: {
        token: string;
        name: string;
        email: string;
        organization: string;
        regime_label: string;
        contract_label: string;
        fixa_noite: boolean;
        status: 'pending' | 'accepted' | 'expired' | 'revoked';
    };
}

const invalidMessages: Record<string, string> = {
    accepted: 'Este convite já foi utilizado. Se já tens conta, entra com o teu email.',
    expired: 'Este convite expirou. Pede à tua organização para reenviar um novo link.',
    revoked: 'Este convite foi revogado. Fala com a tua organização.',
};

export default function AcceptInvitation({ invitation }: AcceptProps) {
    const { data, setData, post, processing, errors } = useForm({
        name: invitation.name,
        password: '',
        password_confirmation: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(`/convite/${invitation.token}`);
    };

    if (invitation.status !== 'pending') {
        return (
            <AuthLayout title={`Convite de ${invitation.organization}`} description={invalidMessages[invitation.status]}>
                <Head title="Convite inválido" />
                <Button asChild className="w-full">
                    <a href="/login">Ir para o login</a>
                </Button>
            </AuthLayout>
        );
    }

    return (
        <AuthLayout
            title={`Bem-vinda à equipa de ${invitation.organization}!`}
            description="Confirma o teu nome e escolhe uma password para criares a tua conta."
        >
            <Head title={`Convite — ${invitation.organization}`} />

            <div className="mb-6 flex flex-wrap items-center justify-center gap-2 text-sm">
                <Badge variant="secondary">{invitation.email}</Badge>
                <Badge variant="outline">{invitation.regime_label}</Badge>
                <Badge variant="outline">{invitation.contract_label}</Badge>
                {invitation.fixa_noite && <Badge variant="outline">Fixa de noite</Badge>}
            </div>

            <form onSubmit={submit} className="flex flex-col gap-6">
                <div className="grid gap-2">
                    <Label htmlFor="name">O teu nome</Label>
                    <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} required autoFocus />
                    <InputError message={errors.name} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="password">Password</Label>
                    <Input
                        id="password"
                        type="password"
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                        required
                        placeholder="Mínimo 8 caracteres"
                    />
                    <InputError message={errors.password} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="password_confirmation">Confirmar password</Label>
                    <Input
                        id="password_confirmation"
                        type="password"
                        value={data.password_confirmation}
                        onChange={(e) => setData('password_confirmation', e.target.value)}
                        required
                    />
                    <InputError message={errors.password_confirmation} />
                </div>

                <Button type="submit" className="w-full" disabled={processing}>
                    {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                    Criar conta e entrar
                </Button>
            </form>
        </AuthLayout>
    );
}
