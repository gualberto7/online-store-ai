import { Link, usePage } from '@inertiajs/react';
import {
    BookOpen,
    Tags,
    FolderGit2,
    LayoutGrid,
    Package,
    ShoppingBag,
    Store,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { TeamSwitcher } from '@/components/team-switcher';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard, home } from '@/routes';
import { index as branchesIndex } from '@/routes/branches';
import { index as productsIndex } from '@/routes/products';
import { index as ordersIndex } from '@/routes/orders';
import { index as categoriesIndex } from '@/routes/categories';
import type { NavItem, Team } from '@/types';

export function AppSidebar() {
    const page = usePage<{ currentTeam: Team | null }>();
    const dashboardUrl = page.props.currentTeam
        ? dashboard(page.props.currentTeam.slug)
        : '/';

    const mainNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboardUrl,
            icon: LayoutGrid,
        },
    ];

    if (page.props.currentTeam) {
        mainNavItems.push({
            title: 'Categorías',
            href: categoriesIndex(page.props.currentTeam.slug),
            icon: Tags,
        });
        mainNavItems.push({
            title: 'Pedidos',
            href: ordersIndex(page.props.currentTeam.slug),
            icon: ShoppingBag,
        });
        mainNavItems.push({
            title: 'Productos',
            href: productsIndex(page.props.currentTeam.slug),
            icon: Package,
        });
        mainNavItems.push({
            title: 'Sucursales',
            href: branchesIndex(page.props.currentTeam.slug),
            icon: Store,
        });
    }

    const footerNavItems: NavItem[] = [
        { title: 'Ver tienda', href: home(), icon: Store },
        {
            title: 'Repository',
            href: 'https://github.com/laravel/react-starter-kit',
            icon: FolderGit2,
        },
        {
            title: 'Documentation',
            href: 'https://laravel.com/docs/starter-kits#react',
            icon: BookOpen,
        },
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboardUrl} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <TeamSwitcher />
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
