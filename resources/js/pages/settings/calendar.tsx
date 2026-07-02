import HeadingSmall from '@/components/heading-small';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Check, Copy } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Calendário',
        href: '/settings/calendario',
    },
];

export default function Calendar({ feed_url: feedUrl }: { feed_url: string }) {
    const [copied, setCopied] = useState(false);
    const [regenerating, setRegenerating] = useState(false);

    const copyUrl = async () => {
        await navigator.clipboard.writeText(feedUrl);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    const regenerate = () => {
        if (!confirm('Regenerar o link invalida o link atual — quem já subscreveu tem de subscrever de novo. Continuar?')) {
            return;
        }

        setRegenerating(true);
        router.post(
            route('calendar.regenerate'),
            {},
            {
                preserveScroll: true,
                onFinish: () => setRegenerating(false),
            },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Calendário" />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall
                        title="Calendário"
                        description="Subscreve o teu feed privado no Google ou Apple Calendar para veres os teus turnos sempre atualizados."
                    />

                    <div className="space-y-2">
                        <Label htmlFor="feed-url">O teu link do feed</Label>
                        <div className="flex items-center gap-2">
                            <Input id="feed-url" value={feedUrl} readOnly onFocus={(e) => e.target.select()} className="font-mono text-xs" />
                            <Button type="button" variant="outline" size="icon" onClick={copyUrl} title="Copiar link">
                                {copied ? <Check className="size-4 text-green-600" /> : <Copy className="size-4" />}
                            </Button>
                        </div>
                        <p className="text-muted-foreground text-sm">Este link é secreto — não o partilhes. Quem o tiver vê os teus turnos.</p>
                    </div>

                    <div className="space-y-4 rounded-lg border p-4">
                        <div>
                            <h3 className="text-sm font-medium">Google Calendar</h3>
                            <p className="text-muted-foreground text-sm">
                                Em &quot;Outros calendários&quot; clica no <strong>+</strong> e escolhe <strong>Por URL</strong>. Cola o link acima
                                e confirma.
                            </p>
                        </div>
                        <div>
                            <h3 className="text-sm font-medium">Apple Calendar</h3>
                            <p className="text-muted-foreground text-sm">
                                Menu <strong>Ficheiro → Nova subscrição de calendário</strong>. Cola o link acima e confirma.
                            </p>
                        </div>
                    </div>

                    <div>
                        <Button type="button" variant="destructive" onClick={regenerate} disabled={regenerating}>
                            Regenerar link
                        </Button>
                        <p className="text-muted-foreground mt-2 text-sm">O link anterior deixa de funcionar assim que regeneras.</p>
                    </div>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
