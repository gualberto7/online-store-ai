import { Head, Link, usePage } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { create, index, show } from '@/routes/orders';

type Props = {
    orders: {
        data: {
            id: number;
            customer_name: string | null;
            status: string;
            total_cents: number;
            branch: { name: string };
        }[];
        current_page: number;
        last_page: number;
    };
    canManage: boolean;
};
const labels: Record<string, string> = {
    draft: 'Borrador',
    confirmed: 'Confirmado',
    cancelled: 'Cancelado',
};

export default function OrdersIndex({ orders, canManage }: Props) {
    const { currentTeam } = usePage().props;
    if (!currentTeam) return null;
    return (
        <>
            <Head title="Pedidos" />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">Pedidos</h1>
                        <p className="text-muted-foreground">
                            Ventas y pedidos de tus sucursales.
                        </p>
                    </div>
                    {canManage && (
                        <Button asChild>
                            <Link href={create(currentTeam.slug)}>
                                Nuevo pedido
                            </Link>
                        </Button>
                    )}
                </div>
                {orders.data.length === 0 ? (
                    <p className="rounded-xl border border-dashed p-10 text-center text-muted-foreground">
                        Todavía no hay pedidos.
                    </p>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {orders.data.map((order) => (
                            <article
                                key={order.id}
                                className="flex flex-col gap-3 rounded-xl border p-5"
                            >
                                <div className="flex items-center justify-between gap-3">
                                    <h2 className="font-semibold">
                                        Pedido #{order.id}
                                    </h2>
                                    <Badge
                                        variant={
                                            order.status === 'confirmed'
                                                ? 'default'
                                                : 'secondary'
                                        }
                                    >
                                        {labels[order.status]}
                                    </Badge>
                                </div>
                                <p className="text-sm">
                                    {order.branch.name} ·{' '}
                                    {order.customer_name ||
                                        'Cliente de mostrador'}
                                </p>
                                <p className="text-lg font-semibold">
                                    {(order.total_cents / 100).toLocaleString(
                                        'es-BO',
                                        {
                                            minimumFractionDigits: 2,
                                            maximumFractionDigits: 2,
                                        },
                                    )}{' '}
                                    Bs
                                </p>
                                <Button
                                    variant="outline"
                                    className="self-start"
                                    asChild
                                >
                                    <Link
                                        href={show({
                                            current_team: currentTeam.slug,
                                            order: order.id,
                                        })}
                                    >
                                        Ver pedido
                                    </Link>
                                </Button>
                            </article>
                        ))}
                    </div>
                )}
                {orders.last_page > 1 && (
                    <nav
                        aria-label="Páginas de pedidos"
                        className="flex items-center gap-3"
                    >
                        {orders.current_page > 1 && (
                            <Button variant="outline" asChild>
                                <Link
                                    href={index(currentTeam.slug, {
                                        query: {
                                            page: orders.current_page - 1,
                                        },
                                    })}
                                >
                                    Anterior
                                </Link>
                            </Button>
                        )}
                        <span>
                            Página {orders.current_page} de {orders.last_page}
                        </span>
                        {orders.current_page < orders.last_page && (
                            <Button variant="outline" asChild>
                                <Link
                                    href={index(currentTeam.slug, {
                                        query: {
                                            page: orders.current_page + 1,
                                        },
                                    })}
                                >
                                    Siguiente
                                </Link>
                            </Button>
                        )}
                    </nav>
                )}
            </div>
        </>
    );
}
OrdersIndex.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: props.currentTeam
        ? [{ title: 'Pedidos', href: index(props.currentTeam.slug) }]
        : [],
});
