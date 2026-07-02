import { Bell } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';

import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';

// Sem websockets (ADR-0005): o sino faz polling a este intervalo.
const POLL_INTERVAL_MS = 30_000;

interface NotificationData {
    type: string;
    message: string;
    [key: string]: unknown;
}

interface NotificationItem {
    id: string;
    data: NotificationData;
    read_at: string | null;
    created_at: string;
}

interface NotificationsResponse {
    notifications: NotificationItem[];
    unread_count: number;
}

function readCookie(name: string): string | null {
    const match = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));

    return match ? decodeURIComponent(match[1]) : null;
}

async function apiFetch(url: string, options: RequestInit = {}): Promise<Response> {
    const headers = new Headers(options.headers);
    headers.set('X-Requested-With', 'XMLHttpRequest');
    headers.set('Accept', 'application/json');

    if (options.method && options.method.toUpperCase() !== 'GET') {
        const token = readCookie('XSRF-TOKEN');

        if (token) {
            headers.set('X-XSRF-TOKEN', token);
        }
    }

    return fetch(url, {
        ...options,
        credentials: 'same-origin',
        headers,
    });
}

function timeAgo(dateString: string): string {
    const seconds = Math.max(0, Math.floor((Date.now() - new Date(dateString).getTime()) / 1000));

    if (seconds < 60) return 'agora mesmo';
    if (seconds < 3600) return `há ${Math.floor(seconds / 60)} min`;
    if (seconds < 86400) return `há ${Math.floor(seconds / 3600)} h`;

    if (seconds < 2_592_000) {
        const dias = Math.floor(seconds / 86400);

        return `há ${dias} ${dias === 1 ? 'dia' : 'dias'}`;
    }

    if (seconds < 31_536_000) {
        const meses = Math.floor(seconds / 2_592_000);

        return `há ${meses} ${meses === 1 ? 'mês' : 'meses'}`;
    }

    const anos = Math.floor(seconds / 31_536_000);

    return `há ${anos} ${anos === 1 ? 'ano' : 'anos'}`;
}

export function NotificationBell() {
    const [notifications, setNotifications] = useState<NotificationItem[]>([]);
    const [unreadCount, setUnreadCount] = useState(0);

    const fetchNotifications = useCallback(async () => {
        try {
            const response = await apiFetch('/notificacoes');

            if (!response.ok) {
                return;
            }

            const payload: NotificationsResponse = await response.json();
            setNotifications(payload.notifications);
            setUnreadCount(payload.unread_count);
        } catch {
            // Falha de rede não deve quebrar a UI; a próxima ronda de polling tenta de novo.
        }
    }, []);

    useEffect(() => {
        fetchNotifications();

        const interval = setInterval(fetchNotifications, POLL_INTERVAL_MS);

        return () => clearInterval(interval);
    }, [fetchNotifications]);

    const markAsRead = useCallback(async (id: string) => {
        setNotifications((current) => current.map((n) => (n.id === id ? { ...n, read_at: n.read_at ?? new Date().toISOString() } : n)));
        setUnreadCount((count) => Math.max(0, count - 1));

        try {
            await apiFetch(`/notificacoes/${id}/lida`, { method: 'POST' });
        } catch {
            // Ignora; o próximo poll reconcilia o estado com o servidor.
        }
    }, []);

    const markAllAsRead = useCallback(async () => {
        setNotifications((current) => current.map((n) => ({ ...n, read_at: n.read_at ?? new Date().toISOString() })));
        setUnreadCount(0);

        try {
            await apiFetch('/notificacoes/lidas', { method: 'POST' });
        } catch {
            // Ignora; o próximo poll reconcilia o estado com o servidor.
        }
    }, []);

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon" className="relative" aria-label="Notificações">
                    <Bell className="h-5 w-5" />
                    {unreadCount > 0 && (
                        <span className="absolute -top-1 -right-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-medium text-white">
                            {unreadCount > 9 ? '9+' : unreadCount}
                        </span>
                    )}
                </Button>
            </DropdownMenuTrigger>

            <DropdownMenuContent align="end" className="w-80">
                <div className="flex items-center justify-between px-2 py-1.5">
                    <DropdownMenuLabel className="p-0">Notificações</DropdownMenuLabel>

                    {unreadCount > 0 && (
                        <button type="button" onClick={markAllAsRead} className="text-muted-foreground hover:text-foreground text-xs">
                            Marcar todas como lidas
                        </button>
                    )}
                </div>

                <DropdownMenuSeparator />

                {notifications.length === 0 ? (
                    <p className="text-muted-foreground px-2 py-4 text-center text-sm">Sem notificações.</p>
                ) : (
                    <div className="max-h-96 overflow-y-auto">
                        {notifications.map((notification) => (
                            <DropdownMenuItem
                                key={notification.id}
                                onSelect={() => {
                                    if (!notification.read_at) {
                                        markAsRead(notification.id);
                                    }
                                }}
                                className={cn('flex flex-col items-start gap-0.5 whitespace-normal', !notification.read_at && 'bg-accent/50')}
                            >
                                <span className="text-sm">{notification.data.message}</span>
                                <span className="text-muted-foreground text-xs">{timeAgo(notification.created_at)}</span>
                            </DropdownMenuItem>
                        ))}
                    </div>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
