import { Form, Head, Link, usePage } from '@inertiajs/react';
import { Download, ChartColumn, Table2 } from 'lucide-react';
import InputError from '@/components/input-error';
import ReportCharts, {
    type TrendPoint,
    type BranchStock,
} from '@/components/report-charts';
import ReportSummaryCards, {
    reportMoney,
    type ReportSummary,
} from '@/components/report-summary';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index, exportMethod as exportReport } from '@/routes/reports';

type Filters = {
    from: string;
    to: string;
    branch: number | '';
    stock: string;
    view: 'details' | 'charts';
};
type InventoryRow = {
    branch_id: number;
    branch: string;
    variant_id: number;
    product: string;
    variant: string;
    sku: string;
    quantity: number;
};
type Props = {
    summary: ReportSummary;
    chartTrend: TrendPoint[];
    stockByBranch: BranchStock[];
    filters: Filters;
    branches: { id: number; name: string; is_active: boolean }[];
    dailySales: {
        date: string;
        orders: number | string;
        amount_cents: number | string;
    }[];
    topProducts: {
        variant_id: number;
        product: string;
        variant: string;
        sku: string;
        units: number | string;
        amount_cents: number | string;
    }[];
    inventory: {
        data: InventoryRow[];
        total: number;
        current_page: number;
        last_page: number;
    };
};
const selectClass =
    'h-9 rounded-md border border-input bg-background px-3 text-sm';
const cellClass = 'px-4 py-3 text-left';

export default function ReportsIndex({
    summary,
    chartTrend,
    stockByBranch,
    filters,
    branches,
    dailySales,
    topProducts,
    inventory,
}: Props) {
    const { currentTeam } = usePage().props;
    if (!currentTeam) return null;
    const csv = (type: string) =>
        exportReport.url(currentTeam.slug, { query: { ...filters, type } });
    const maxDaily = Math.max(
        1,
        ...dailySales.map((day) => Number(day.amount_cents)),
    );
    return (
        <>
            <Head title="Reportes" />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <header>
                    <h1 className="text-2xl font-semibold">Reportes</h1>
                    <p className="text-muted-foreground">
                        Ventas, devoluciones e inventario de tu empresa.
                    </p>
                </header>
                <nav
                    aria-label="Vistas de reportes"
                    className="flex w-fit gap-1 rounded-lg border bg-muted p-1"
                >
                    <Button
                        variant={
                            filters.view === 'details' ? 'default' : 'ghost'
                        }
                        asChild
                    >
                        <Link
                            href={index(currentTeam.slug, {
                                query: { ...filters, view: 'details' },
                            })}
                            aria-current={
                                filters.view === 'details' ? 'page' : undefined
                            }
                            preserveScroll
                        >
                            <Table2 className="size-4" aria-hidden="true" />
                            Detalle
                        </Link>
                    </Button>
                    <Button
                        variant={
                            filters.view === 'charts' ? 'default' : 'ghost'
                        }
                        asChild
                    >
                        <Link
                            href={index(currentTeam.slug, {
                                query: { ...filters, view: 'charts' },
                            })}
                            aria-current={
                                filters.view === 'charts' ? 'page' : undefined
                            }
                            preserveScroll
                        >
                            <ChartColumn
                                className="size-4"
                                aria-hidden="true"
                            />
                            Gráficas
                        </Link>
                    </Button>
                </nav>
                <Form
                    key={JSON.stringify(filters)}
                    {...index.form(currentTeam.slug)}
                    className="grid gap-4 rounded-xl border p-5"
                >
                    {({ errors, processing }) => (
                        <>
                            <input
                                type="hidden"
                                name="view"
                                value={filters.view}
                            />
                            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="from">Desde</Label>
                                    <Input
                                        id="from"
                                        name="from"
                                        type="date"
                                        defaultValue={filters.from}
                                        required
                                    />
                                    <InputError message={errors.from} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="to">Hasta</Label>
                                    <Input
                                        id="to"
                                        name="to"
                                        type="date"
                                        defaultValue={filters.to}
                                        required
                                    />
                                    <InputError message={errors.to} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="branch">Sucursal</Label>
                                    <select
                                        id="branch"
                                        name="branch"
                                        defaultValue={filters.branch}
                                        className={selectClass}
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
                                                {branch.is_active
                                                    ? ''
                                                    : ' (inactiva)'}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.branch} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="stock">Inventario</Label>
                                    <select
                                        id="stock"
                                        name="stock"
                                        defaultValue={filters.stock}
                                        className={selectClass}
                                    >
                                        <option value="all">
                                            Todas las existencias
                                        </option>
                                        <option value="empty">
                                            Solo sin existencias
                                        </option>
                                    </select>
                                    <InputError message={errors.stock} />
                                </div>
                            </div>
                            <div className="flex flex-wrap gap-3">
                                <Button disabled={processing}>
                                    {processing
                                        ? 'Consultando…'
                                        : 'Aplicar filtros'}
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link
                                        href={index(currentTeam.slug, {
                                            query: { view: filters.view },
                                        })}
                                    >
                                        Este mes
                                    </Link>
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
                <p className="text-sm text-muted-foreground">
                    Período: {filters.from} al {filters.to}, ambas fechas
                    incluidas. Ventas según fecha de entrega; devoluciones según
                    fecha del reembolso. El neto puede ser negativo si se
                    reembolsan ventas de períodos anteriores. No representa
                    utilidad.
                </p>
                <ReportSummaryCards summary={summary} />
                <section
                    className="grid gap-3 rounded-xl border p-5"
                    aria-labelledby="exports-title"
                >
                    <h2 id="exports-title" className="font-semibold">
                        Exportar CSV
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        Descarga todos los registros de los filtros aplicados,
                        incluidas las páginas no visibles.
                    </p>
                    <div className="flex flex-wrap gap-3">
                        {[
                            ['sales', 'Ventas'],
                            ['returns', 'Devoluciones'],
                            ['products', 'Productos vendidos'],
                            ['inventory', 'Inventario actual'],
                        ].map(([type, title]) => (
                            <Button key={type} variant="outline" asChild>
                                <a href={csv(type)}>
                                    <Download
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    {title}
                                </a>
                            </Button>
                        ))}
                    </div>
                </section>
                {filters.view === 'charts' ? (
                    <ReportCharts
                        trend={chartTrend}
                        products={topProducts}
                        stock={stockByBranch}
                    />
                ) : (
                    <>
                        <section
                            className="overflow-hidden rounded-xl border"
                            aria-labelledby="sales-title"
                        >
                            <div className="border-b p-5">
                                <h2 id="sales-title" className="font-semibold">
                                    Ventas por día
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    Ventas brutas. Se muestran los días con
                                    pedidos entregados.
                                </p>
                            </div>
                            {dailySales.length === 0 ? (
                                <p className="p-6 text-muted-foreground">
                                    No hay ventas entregadas en este período.
                                </p>
                            ) : (
                                <div className="max-h-96 overflow-auto">
                                    <table className="w-full text-sm">
                                        <thead className="bg-muted">
                                            <tr>
                                                <th className={cellClass}>
                                                    Fecha
                                                </th>
                                                <th className={cellClass}>
                                                    Pedidos
                                                </th>
                                                <th className={cellClass}>
                                                    Ventas brutas
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {dailySales.map((day) => (
                                                <tr
                                                    key={day.date}
                                                    className="border-t"
                                                >
                                                    <td className={cellClass}>
                                                        {day.date}
                                                    </td>
                                                    <td className={cellClass}>
                                                        {day.orders}
                                                    </td>
                                                    <td
                                                        className={`${cellClass} min-w-48`}
                                                    >
                                                        <div className="flex flex-col gap-2">
                                                            <span className="tabular-nums">
                                                                {reportMoney(
                                                                    day.amount_cents,
                                                                )}
                                                            </span>
                                                            <div
                                                                aria-hidden="true"
                                                                className="h-1.5 rounded-full bg-muted"
                                                            >
                                                                <div
                                                                    className="h-full rounded-full bg-primary"
                                                                    style={{
                                                                        width: `${(Number(day.amount_cents) / maxDaily) * 100}%`,
                                                                    }}
                                                                />
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </section>
                        <section
                            className="overflow-hidden rounded-xl border"
                            aria-labelledby="products-title"
                        >
                            <div className="border-b p-5">
                                <h2
                                    id="products-title"
                                    className="font-semibold"
                                >
                                    10 variantes más vendidas
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    Ordenadas por unidades vendidas, antes de
                                    devoluciones. Los importes usan el precio
                                    registrado en cada pedido.
                                </p>
                            </div>
                            {topProducts.length === 0 ? (
                                <p className="p-6 text-muted-foreground">
                                    No hay productos vendidos en este período.
                                </p>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead className="bg-muted">
                                            <tr>
                                                {[
                                                    'Producto / variante',
                                                    'SKU',
                                                    'Unidades',
                                                    'Venta bruta',
                                                ].map((label) => (
                                                    <th
                                                        key={label}
                                                        className={cellClass}
                                                    >
                                                        {label}
                                                    </th>
                                                ))}
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {topProducts.map((product) => (
                                                <tr
                                                    key={product.variant_id}
                                                    className="border-t"
                                                >
                                                    <td className={cellClass}>
                                                        <p className="font-medium">
                                                            {product.product}
                                                        </p>
                                                        <p className="text-muted-foreground">
                                                            {product.variant}
                                                        </p>
                                                    </td>
                                                    <td className={cellClass}>
                                                        {product.sku}
                                                    </td>
                                                    <td
                                                        className={`${cellClass} tabular-nums`}
                                                    >
                                                        {product.units}
                                                    </td>
                                                    <td
                                                        className={`${cellClass} whitespace-nowrap tabular-nums`}
                                                    >
                                                        {reportMoney(
                                                            product.amount_cents,
                                                        )}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </section>
                        <section
                            className="overflow-hidden rounded-xl border"
                            aria-labelledby="inventory-title"
                        >
                            <div className="border-b p-5">
                                <h2
                                    id="inventory-title"
                                    className="font-semibold"
                                >
                                    Inventario actual
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    {inventory.total} combinaciones de variante
                                    y sucursal. Solo productos, variantes y
                                    sucursales activos. El rango de fechas no
                                    modifica estas existencias.
                                </p>
                            </div>
                            {inventory.data.length === 0 ? (
                                <p className="p-6 text-muted-foreground">
                                    No hay existencias que coincidan con estos
                                    filtros.
                                </p>
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="w-full text-sm">
                                        <thead className="bg-muted">
                                            <tr>
                                                {[
                                                    'Sucursal',
                                                    'Producto / variante',
                                                    'SKU',
                                                    'Existencias',
                                                ].map((label) => (
                                                    <th
                                                        key={label}
                                                        className={cellClass}
                                                    >
                                                        {label}
                                                    </th>
                                                ))}
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {inventory.data.map((row) => (
                                                <tr
                                                    key={`${row.branch_id}-${row.variant_id}`}
                                                    className="border-t"
                                                >
                                                    <td className={cellClass}>
                                                        {row.branch}
                                                    </td>
                                                    <td className={cellClass}>
                                                        <p className="font-medium">
                                                            {row.product}
                                                        </p>
                                                        <p className="text-muted-foreground">
                                                            {row.variant}
                                                        </p>
                                                    </td>
                                                    <td className={cellClass}>
                                                        {row.sku}
                                                    </td>
                                                    <td
                                                        className={`${cellClass} font-medium tabular-nums`}
                                                    >
                                                        {Number(
                                                            row.quantity,
                                                        ) === 0
                                                            ? 'Sin existencias'
                                                            : row.quantity}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </section>
                        {inventory.last_page > 1 && (
                            <nav
                                aria-label="Páginas de inventario"
                                className="flex flex-wrap items-center gap-3"
                            >
                                {inventory.current_page > 1 && (
                                    <Button variant="outline" asChild>
                                        <Link
                                            href={index(currentTeam.slug, {
                                                query: {
                                                    ...filters,
                                                    page:
                                                        inventory.current_page -
                                                        1,
                                                },
                                            })}
                                            preserveScroll
                                        >
                                            Anterior
                                        </Link>
                                    </Button>
                                )}
                                <span className="text-sm">
                                    Página {inventory.current_page} de{' '}
                                    {inventory.last_page}
                                </span>
                                {inventory.current_page <
                                    inventory.last_page && (
                                    <Button variant="outline" asChild>
                                        <Link
                                            href={index(currentTeam.slug, {
                                                query: {
                                                    ...filters,
                                                    page:
                                                        inventory.current_page +
                                                        1,
                                                },
                                            })}
                                            preserveScroll
                                        >
                                            Siguiente
                                        </Link>
                                    </Button>
                                )}
                            </nav>
                        )}
                    </>
                )}
            </div>
        </>
    );
}
ReportsIndex.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: props.currentTeam
        ? [{ title: 'Reportes', href: index(props.currentTeam.slug) }]
        : [],
});
