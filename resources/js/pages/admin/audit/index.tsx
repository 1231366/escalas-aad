import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type AuditLogEntry, type BreadcrumbItem, type Paginated } from '@/types';
import { Head, router } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Auditoria', href: '/admin/auditoria' }];

interface Props {
    logs: Paginated<AuditLogEntry>;
    actions: string[];
    filters: { action: string | null };
}

const ALL_ACTIONS = '__all__';

export default function AuditLogIndex({ logs, actions, filters }: Props) {
    const onFilterChange = (value: string) => {
        router.get(
            '/admin/auditoria',
            value === ALL_ACTIONS ? {} : { action: value },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const goToPage = (url: string | null) => {
        if (!url) return;
        router.get(url, {}, { preserveState: true, preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Auditoria" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-xl font-semibold">Auditoria</h1>
                        <p className="text-muted-foreground text-sm">Histórico de mutações da organização — quem, o quê, quando.</p>
                    </div>

                    <div className="w-56">
                        <Select value={filters.action ?? ALL_ACTIONS} onValueChange={onFilterChange}>
                            <SelectTrigger>
                                <SelectValue placeholder="Todas as ações" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL_ACTIONS}>Todas as ações</SelectItem>
                                {actions.map((action) => (
                                    <SelectItem key={action} value={action}>
                                        {action}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                {logs.data.length === 0 ? (
                    <div className="text-muted-foreground rounded-xl border p-8 text-center text-sm">
                        Sem registos de auditoria{filters.action ? ` para a ação "${filters.action}"` : ''}.
                    </div>
                ) : (
                    <div className="overflow-x-auto rounded-xl border">
                        <table className="w-full border-collapse text-sm">
                            <thead>
                                <tr className="bg-muted/40 border-b text-left">
                                    <th className="p-2 font-medium whitespace-nowrap">Quando</th>
                                    <th className="p-2 font-medium whitespace-nowrap">Quem</th>
                                    <th className="p-2 font-medium whitespace-nowrap">Ação</th>
                                    <th className="p-2 font-medium whitespace-nowrap">Entidade</th>
                                    <th className="p-2 font-medium">Resumo</th>
                                </tr>
                            </thead>
                            <tbody>
                                {logs.data.map((log) => (
                                    <tr key={log.id} className="border-b last:border-0 align-top">
                                        <td className="text-muted-foreground p-2 whitespace-nowrap tabular-nums">
                                            {log.created_at ? new Date(log.created_at).toLocaleString('pt-PT') : '—'}
                                        </td>
                                        <td className="p-2 whitespace-nowrap">{log.actor_name}</td>
                                        <td className="p-2 font-mono text-xs whitespace-nowrap">{log.action}</td>
                                        <td className="text-muted-foreground p-2 whitespace-nowrap">
                                            {log.subject_type ? `${log.subject_type} #${log.subject_id}` : '—'}
                                        </td>
                                        <td className="max-w-md truncate p-2 font-mono text-xs" title={log.changes_summary ?? undefined}>
                                            {log.changes_summary ?? '—'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {logs.last_page > 1 && (
                    <div className="flex items-center justify-between text-sm">
                        <p className="text-muted-foreground">
                            {logs.from}–{logs.to} de {logs.total}
                        </p>
                        <div className="flex items-center gap-2">
                            {logs.links.map((link, index) => (
                                <button
                                    key={index}
                                    type="button"
                                    disabled={!link.url}
                                    onClick={() => goToPage(link.url)}
                                    className={`rounded border px-2.5 py-1 text-xs disabled:cursor-not-allowed disabled:opacity-40 ${
                                        link.active ? 'bg-primary text-primary-foreground border-primary' : 'hover:bg-muted'
                                    }`}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
