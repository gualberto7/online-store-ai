import { Form, Head, Link, usePage } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { create, index, show } from '@/routes/orders';

type Props = {
    orders: {
        data: {
            id: number;
            customer_name: string | null;
            status: string;
            completed_at: string | null;
            total_cents: number;
            customer_phone: string | null;
            created_at: string;
            branch: { name: string };
        }[];
        total: number;
        current_page: number;
        last_page: number;
    };
    branches: { id: number; name: string; is_active: boolean }[];
    filters: {
        q: string;
        branch: number | '';
        status: string;
        from: string;
        to: string;
    };
    canManage: boolean;
};
const labels: Record<string, string> = {
    draft: 'Borrador',
    confirmed: 'Pendiente de retiro',
    cancelled: 'Cancelado',
};

export default function OrdersIndex({
    orders,
    canManage,
    branches,
    filters,
}: Props) {
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
                <Form
                    key={JSON.stringify(filters)}
                    {...index.form(currentTeam.slug)}
                    className="grid gap-4 rounded-xl border p-5"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                <div className="grid gap-2">
                                    <Label htmlFor="q">Buscar pedido</Label>
                                    <Input
                                        id="q"
                                        name="q"
                                        defaultValue={filters.q}
                                        placeholder="Número, cliente o teléfono"
                                        maxLength={120}
                                    />
                                    <InputError message={errors.q} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="branch">Sucursal</Label>
                                    <select
                                        id="branch"
                                        name="branch"
                                        defaultValue={filters.branch}
                                        className="h-9 rounded-md border border-input bg-background px-3 text-sm"
                                    >
                                        <option value="">
                                            Todas las sucursales
                                        </option>
                                        {branches.map((branch) => (
                                            <option
                                                key={branch.id}
                                                value={branch.id}
                                            >
                                                {branch.name}
                                                {!branch.is_active
                                                    ? ' (inactiva)'
                                                    : ''}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.branch} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="status">Estado</Label>
                                    <select
                                        id="status"
                                        name="status"
                                        defaultValue={filters.status}
                                        className="h-9 rounded-md border border-input bg-background px-3 text-sm"
                                    >
                                        <option value="">
                                            Todos los estados
                                        </option>
                                        <option value="draft">Borrador</option>
                                        <option value="pending">
                                            Pendiente de retiro
                                        </option>
                                        <option value="completed">
                                            Pagado y entregado
                                        </option>
                                        <option value="cancelled">
                                            Cancelado
                                        </option>
                                    </select>
                                    <InputError message={errors.status} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="from">Creado desde</Label>
                                    <Input
                                        id="from"
                                        name="from"
                                        type="date"
                                        defaultValue={filters.from}
                                    />
                                    <InputError message={errors.from} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="to">Creado hasta</Label>
                                    <Input
                                        id="to"
                                        name="to"
                                        type="date"
                                        defaultValue={filters.to}
                                    />
                                    <InputError message={errors.to} />
                                </div>
                            </div>
                            <div className="flex flex-wrap items-center gap-3">
                                <Button type="submit" disabled={processing}>
                                    {processing
                                        ? 'Buscando…'
                                        : 'Aplicar filtros'}
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link href={index(currentTeam.slug)}>
                                        Limpiar filtros
                                    </Link>
                                </Button>
                                <p
                                    className="text-sm text-muted-foreground"
                                    aria-live="polite"
                                >
                                    {orders.total}{' '}
                                    {orders.total === 1
                                        ? 'pedido encontrado'
                                        : 'pedidos encontrados'}
                                </p>
                            </div>
                        </>
                    )}
                </Form>
                {orders.data.length === 0 ? (
                    <p className="rounded-xl border border-dashed p-10 text-center text-muted-foreground">
                        No se encontraron pedidos con estos filtros.
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
                                        {order.completed_at
                                            ? 'Pagado y entregado'
                                            : labels[order.status]}
                                    </Badge>
                                </div>
                                <p className="text-sm">
                                    {order.branch.name} ·{' '}
                                    {order.customer_name ||
                                        'Cliente de mostrador'}
                                </p>
                                {order.customer_phone && (
                                    <p className="text-sm text-muted-foreground">
                                        Teléfono: {order.customer_phone}
                                    </p>
                                )}
                                <p className="text-sm text-muted-foreground">
                                    Creado:{' '}
                                    {new Date(order.created_at).toLocaleString(
                                        'es-BO',
                                    )}
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
                                            ...filters,
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
                                            ...filters,
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
