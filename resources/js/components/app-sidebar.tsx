import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { ArrowLeftRight, CalendarDays, LayoutGrid, Palmtree, ScrollText, Settings2, UserCog, UserPlus, Users } from 'lucide-react';
import AppLogo from './app-logo';

const mainNavItems: NavItem[] = [
    {
        title: 'Painel',
        url: '/dashboard',
        icon: LayoutGrid,
    },
    {
        title: 'Escala',
        url: '/escala',
        icon: CalendarDays,
    },
    {
        title: 'Trocas',
        url: '/trocas',
        icon: ArrowLeftRight,
    },
    {
        title: 'Férias',
        url: '/ferias',
        icon: Palmtree,
    },
];

const adminNavItems: NavItem[] = [
    {
        title: 'Funcionárias',
        url: '/admin/funcionarios',
        icon: Users,
    },
    {
        title: 'Convites',
        url: '/admin/convites',
        icon: UserPlus,
    },
    {
        title: 'Regras',
        url: '/admin/regras',
        icon: Settings2,
    },
    {
        title: 'Escalas',
        url: '/admin/escalas',
        icon: CalendarDays,
    },
    {
        title: 'Trocas (aprovar)',
        url: '/admin/trocas',
        icon: ArrowLeftRight,
    },
    {
        title: 'Férias (aprovar)',
        url: '/admin/ferias',
        icon: Palmtree,
    },
    {
        title: 'Ausências',
        url: '/admin/ausencias',
        icon: UserCog,
    },
    {
        title: 'Auditoria',
        url: '/admin/auditoria',
        icon: ScrollText,
    },
];

// Autogestão de Trocas/Férias — o admin não tem perfil de funcionária, só
// vê e decide os pedidos em "Trocas (aprovar)"/"Férias (aprovar)".
const selfServiceOnlyTitles = ['Trocas', 'Férias'];

export function AppSidebar() {
    const { auth } = usePage<SharedData>().props;
    const navItems = auth.isAdmin
        ? [...mainNavItems.filter((item) => !selfServiceOnlyTitles.includes(item.title)), ...adminNavItems]
        : mainNavItems;

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/dashboard" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={navItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
