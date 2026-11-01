import { Head, Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import PendingInvitationsModal from '@/components/pending-invitations-modal';
import ReportSummaryCards, {
    type ReportSummary,
} from '@/components/report-summary';
import { Button } from '@/components/ui/button';
import { index as reportsIndex } from '@/routes/reports';
import { index as productsIndex } from '@/routes/products';
import { index as branchesIndex } from '@/routes/branches';
import { dashboard } from '@/routes';
import type { DashboardInvitation } from '@/types';

type Props = {
    reportSummary?: ReportSummary | null;
    reportPeriod?: { from: string; to: string };
    pendingInvitations?: DashboardInvitation[];
};

export default function Dashboard({
    pendingInvitations = [],
    reportSummary,
    reportPeriod,
}: Props) {
    const { currentTeam } = usePage().props;
    const [showInvitations, setShowInvitations] = useState(
        pendingInvitations.length > 0,
    );

    return (
        <>
            <Head title="Dashboard" />
            <PendingInvitationsModal
                invitations={pendingInvitations}
                open={pendingInvitations.length > 0 && showInvitations}
                onOpenChange={setShowInvitations}
            />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div>
                    <h1 className="text-2xl font-semibold">Dashboard</h1>
                    <p className="text-muted-foreground">
                        {currentTeam?.name ?? 'Tu espacio de trabajo'}
                    </p>
                </div>
                {reportSummary ? (
                    <>
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <p className="text-sm text-muted-foreground">
                                Resumen del mes · {reportPeriod?.from} al{' '}
                                {reportPeriod?.to}
                            </p>
                            {currentTeam && (
                                <Button variant="outline" asChild>
                                    <Link href={reportsIndex(currentTeam.slug)}>
                                        Ver reportes
                                    </Link>
                                </Button>
                            )}
                        </div>
                        <ReportSummaryCards summary={reportSummary} />
                        <p className="text-sm text-muted-foreground">
                            Ventas de pedidos pagados y entregados menos
                            devoluciones registradas este mes. El inventario
                            corresponde a las existencias actuales.
                        </p>
                    </>
                ) : (
                    <p className="rounded-xl border p-6 text-muted-foreground">
                        Bienvenido. Desde el menú puedes consultar los productos
                        y las sucursales de tu equipo. Los reportes están
                        disponibles para propietarios y administradores de
                        empresas.
                    </p>
                )}
                {currentTeam && (
                    <div className="flex flex-wrap gap-3">
                        <Button variant="outline" asChild>
                            <Link href={productsIndex(currentTeam.slug)}>
                                Ver productos
                            </Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href={branchesIndex(currentTeam.slug)}>
                                Ver sucursales
                            </Link>
                        </Button>
                    </div>
                )}
            </div>
        </>
    );
}

Dashboard.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: props.currentTeam ? dashboard(props.currentTeam.slug) : '/',
        },
    ],
});
