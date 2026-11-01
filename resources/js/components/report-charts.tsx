import { useState } from 'react';
import { reportMoney } from '@/components/report-summary';

export type TrendPoint = {
    from: string;
    to: string;
    sales_cents: number;
    refunds_cents: number;
    net_cents: number;
};
export type TopProduct = {
    variant_id: number;
    product: string;
    variant: string;
    sku: string;
    units: number | string;
    amount_cents: number | string;
};
export type BranchStock = {
    branch_id: number;
    branch: string;
    units: number | string;
    variants: number | string;
    empty_count: number | string;
};

function shortDate(date: string) {
    return `${date.slice(8, 10)}/${date.slice(5, 7)}/${date.slice(2, 4)}`;
}
function period(point: TrendPoint) {
    return point.from === point.to
        ? point.from
        : `${point.from} al ${point.to}`;
}

function SalesTrend({ points }: { points: TrendPoint[] }) {
    const [selected, setSelected] = useState<number | null>(null);
    const [metric, setMetric] = useState<
        'sales_cents' | 'refunds_cents' | 'net_cents'
    >('sales_cents');
    const hasData = points.some(
        (point) => point.sales_cents !== 0 || point.refunds_cents !== 0,
    );
    const values = points.map((point) => point[metric]);
    const min = Math.min(0, ...values);
    const max = Math.max(100, ...values);
    const width = 900;
    const height = 280;
    const left = 98;
    const right = 24;
    const top = 24;
    const bottom = 44;
    const x = (index: number) =>
        left +
        (points.length === 1
            ? (width - left - right) / 2
            : (index / (points.length - 1)) * (width - left - right));
    const y = (value: number) =>
        top + ((max - value) / (max - min)) * (height - top - bottom);
    const selectedPoint = selected === null ? null : points[selected];
    const tickIndices = [
        ...new Set([0, Math.floor((points.length - 1) / 2), points.length - 1]),
    ].filter((index) => index >= 0);
    return (
        <section
            className="min-w-0 overflow-hidden rounded-xl border bg-card text-card-foreground"
            aria-labelledby="trend-title"
        >
            <div className="flex flex-wrap items-center justify-between gap-4 border-b p-5">
                <div>
                    <h2 id="trend-title" className="font-semibold">
                        Evolución del período
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        Importes en bolivianos. Los días sin movimientos cuentan
                        como cero.
                    </p>
                </div>
                <label className="flex items-center gap-2 text-sm">
                    Mostrar
                    <select
                        value={metric}
                        onChange={(event) => {
                            setMetric(event.target.value as typeof metric);
                            setSelected(null);
                        }}
                        className="h-9 rounded-md border border-input bg-background px-3"
                    >
                        <option value="sales_cents">Ventas brutas</option>
                        <option value="refunds_cents">Devoluciones</option>
                        <option value="net_cents">Neto</option>
                    </select>
                </label>
            </div>
            {!hasData ? (
                <p className="p-8 text-center text-muted-foreground">
                    No hay ventas ni devoluciones en este período.
                </p>
            ) : (
                <>
                    <div className="overflow-x-auto p-4">
                        <svg
                            viewBox={`0 0 ${width} ${height}`}
                            className="h-auto w-full min-w-[560px]"
                            aria-label={`Gráfica de ${metric === 'sales_cents' ? 'ventas brutas' : metric === 'refunds_cents' ? 'devoluciones' : 'neto'} por período`}
                        >
                            <title>Evolución de importes en bolivianos</title>
                            {[0, 1, 2, 3, 4].map((tick) => {
                                const value = min + ((max - min) * tick) / 4;
                                return (
                                    <g key={tick}>
                                        <line
                                            x1={left}
                                            x2={width - right}
                                            y1={y(value)}
                                            y2={y(value)}
                                            className="stroke-border"
                                            strokeDasharray="4 4"
                                        />
                                        <text
                                            x={left - 12}
                                            y={y(value) + 4}
                                            textAnchor="end"
                                            className="fill-muted-foreground text-[11px]"
                                        >
                                            {(value / 100).toLocaleString(
                                                'es-BO',
                                                { maximumFractionDigits: 2 },
                                            )}
                                        </text>
                                    </g>
                                );
                            })}
                            {min < 0 && (
                                <line
                                    x1={left}
                                    x2={width - right}
                                    y1={y(0)}
                                    y2={y(0)}
                                    className="stroke-muted-foreground"
                                />
                            )}
                            <polyline
                                points={points
                                    .map(
                                        (point, index) =>
                                            `${x(index)},${y(point[metric])}`,
                                    )
                                    .join(' ')}
                                fill="none"
                                className="stroke-sky-600 dark:stroke-sky-400"
                                strokeWidth={3}
                                strokeLinejoin="round"
                            />
                            {points.map((point, index) => (
                                <g key={point.from}>
                                    <circle
                                        cx={x(index)}
                                        cy={y(point[metric])}
                                        r={
                                            selected === index ||
                                            points.length === 1
                                                ? 5
                                                : 2
                                        }
                                        className="fill-sky-600 dark:fill-sky-400"
                                    />
                                    <rect
                                        x={
                                            x(index) -
                                            (points.length > 1
                                                ? (width - left - right) /
                                                  (points.length - 1) /
                                                  2
                                                : 16)
                                        }
                                        y={top}
                                        width={
                                            points.length > 1
                                                ? (width - left - right) /
                                                  (points.length - 1)
                                                : 32
                                        }
                                        height={height - top - bottom}
                                        fill="transparent"
                                        tabIndex={0}
                                        role="img"
                                        aria-label={`${period(point)}: ${reportMoney(point[metric])}`}
                                        onMouseEnter={() => setSelected(index)}
                                        onMouseLeave={() => setSelected(null)}
                                        onFocus={() => setSelected(index)}
                                        onBlur={() => setSelected(null)}
                                        className="outline-none focus:fill-sky-500/10"
                                    >
                                        <title>
                                            {period(point)}:{' '}
                                            {reportMoney(point[metric])}
                                        </title>
                                    </rect>
                                </g>
                            ))}
                            {tickIndices.map((index) => (
                                <text
                                    key={index}
                                    x={x(index)}
                                    y={height - 12}
                                    textAnchor="middle"
                                    className="fill-muted-foreground text-[11px]"
                                >
                                    {shortDate(points[index].from)}
                                </text>
                            ))}
                        </svg>
                    </div>
                    <div
                        className="border-t px-5 py-3 text-sm text-muted-foreground"
                        aria-live="polite"
                    >
                        {selectedPoint ? (
                            <p>
                                <span className="font-medium text-foreground">
                                    {period(selectedPoint)}
                                </span>{' '}
                                · Ventas{' '}
                                {reportMoney(selectedPoint.sales_cents)} ·
                                Devoluciones{' '}
                                {reportMoney(selectedPoint.refunds_cents)} ·
                                Neto {reportMoney(selectedPoint.net_cents)}
                            </p>
                        ) : (
                            <p>
                                Señala un punto o navega con Tab para ver sus
                                importes.{' '}
                                {points.some(
                                    (point) => point.from !== point.to,
                                ) &&
                                    'Los períodos extensos se agrupan en intervalos; cada punto suma los movimientos del intervalo.'}
                            </p>
                        )}
                    </div>
                </>
            )}
        </section>
    );
}

export default function ReportCharts({
    trend,
    products,
    stock,
}: {
    trend: TrendPoint[];
    products: TopProduct[];
    stock: BranchStock[];
}) {
    const maxUnits = Math.max(
        1,
        ...products.map((product) => Number(product.units)),
    );
    const maxStock = Math.max(
        1,
        ...stock.map((branch) => Number(branch.units)),
    );
    return (
        <div className="grid min-w-0 gap-6">
            <SalesTrend
                key={trend
                    .map(
                        (point) =>
                            `${point.from}:${point.sales_cents}:${point.refunds_cents}`,
                    )
                    .join('|')}
                points={trend}
            />
            <div className="grid min-w-0 gap-6 xl:grid-cols-2">
                <section
                    className="min-w-0 rounded-xl border bg-card p-5 text-card-foreground"
                    aria-labelledby="top-chart-title"
                >
                    <h2 id="top-chart-title" className="font-semibold">
                        Variantes más vendidas
                    </h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Hasta 10 variantes. Unidades vendidas antes de
                        devoluciones.
                    </p>
                    {products.length === 0 ? (
                        <p className="py-8 text-center text-muted-foreground">
                            No hay productos vendidos en este período.
                        </p>
                    ) : (
                        <ol className="mt-6 grid gap-5">
                            {products.map((product) => (
                                <li
                                    key={product.variant_id}
                                    className="grid gap-2"
                                >
                                    <div className="flex items-start justify-between gap-3 text-sm">
                                        <div className="min-w-0">
                                            <p className="font-medium break-words">
                                                {product.product}
                                            </p>
                                            <p className="text-xs break-words text-muted-foreground">
                                                {product.variant} ·{' '}
                                                {product.sku}
                                            </p>
                                        </div>
                                        <p className="shrink-0 text-right tabular-nums">
                                            <span className="font-semibold">
                                                {product.units} uds.
                                            </span>
                                            <span className="block text-xs text-muted-foreground">
                                                {reportMoney(
                                                    product.amount_cents,
                                                )}
                                            </span>
                                        </p>
                                    </div>
                                    <div
                                        role="img"
                                        aria-label={`${product.product}, ${product.variant}: ${product.units} unidades`}
                                        className="h-3 overflow-hidden rounded-full bg-muted"
                                    >
                                        <div
                                            className="h-full rounded-full bg-sky-600 dark:bg-sky-400"
                                            style={{
                                                width: `${(Number(product.units) / maxUnits) * 100}%`,
                                            }}
                                        />
                                    </div>
                                </li>
                            ))}
                        </ol>
                    )}
                </section>
                <section
                    className="min-w-0 rounded-xl border bg-card p-5 text-card-foreground"
                    aria-labelledby="stock-chart-title"
                >
                    <h2 id="stock-chart-title" className="font-semibold">
                        Existencias por sucursal
                    </h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Unidades actuales de las variantes activas según los
                        filtros de sucursal e inventario. No dependen del rango
                        de fechas.
                    </p>
                    {stock.length === 0 ? (
                        <p className="py-8 text-center text-muted-foreground">
                            No hay inventario que coincida con estos filtros.
                        </p>
                    ) : (
                        <ul className="mt-6 grid gap-6">
                            {stock.map((branch) => (
                                <li
                                    key={branch.branch_id}
                                    className="grid gap-2"
                                >
                                    <div className="flex items-center justify-between gap-3 text-sm">
                                        <span className="font-medium break-words">
                                            {branch.branch}
                                        </span>
                                        <span className="shrink-0 font-semibold tabular-nums">
                                            {branch.units} uds.
                                        </span>
                                    </div>
                                    <div
                                        role="img"
                                        aria-label={`${branch.branch}: ${branch.units} unidades en stock`}
                                        className="h-5 overflow-hidden rounded-md bg-muted"
                                    >
                                        <div
                                            className="h-full rounded-md bg-teal-600 dark:bg-teal-400"
                                            style={{
                                                width: `${(Number(branch.units) / maxStock) * 100}%`,
                                            }}
                                        />
                                    </div>
                                    <p className="text-xs text-muted-foreground">
                                        {branch.variants} variantes ·{' '}
                                        {branch.empty_count} sin existencias
                                    </p>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </div>
    );
}
