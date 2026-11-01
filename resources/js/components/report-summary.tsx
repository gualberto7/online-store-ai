export type ReportSummary = {
    sales_cents: number;
    refunds_cents: number;
    net_cents: number;
    orders: number;
    units_in_stock: number;
    out_of_stock: number;
};

export function reportMoney(cents: number | string) {
    return `${(Number(cents) / 100).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} Bs`;
}

export default function ReportSummaryCards({
    summary,
}: {
    summary: ReportSummary;
}) {
    const cards = [
        {
            label: 'Ventas brutas',
            value: reportMoney(summary.sales_cents),
            detail: `${summary.orders} ${summary.orders === 1 ? 'pedido pagado y entregado' : 'pedidos pagados y entregados'}`,
        },
        {
            label: 'Devoluciones',
            value: reportMoney(summary.refunds_cents),
            detail: 'Reembolsos registrados en el período',
        },
        {
            label: 'Neto del período',
            value: reportMoney(summary.net_cents),
            detail: 'Ventas brutas menos reembolsos',
        },
        {
            label: 'Unidades en stock',
            value: summary.units_in_stock.toLocaleString('es-BO'),
            detail: 'Existencias actuales en sucursales activas',
        },
        {
            label: 'Sin existencias',
            value: summary.out_of_stock.toLocaleString('es-BO'),
            detail: 'Combinaciones de variante y sucursal con saldo cero',
        },
    ];
    return (
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            {cards.map((card) => (
                <article
                    key={card.label}
                    className="flex flex-col gap-2 rounded-xl border bg-card p-5 text-card-foreground"
                >
                    <h2 className="text-sm font-medium text-muted-foreground">
                        {card.label}
                    </h2>
                    <p className="text-2xl font-semibold tracking-tight tabular-nums">
                        {card.value}
                    </p>
                    <p className="text-xs text-muted-foreground">
                        {card.detail}
                    </p>
                </article>
            ))}
        </div>
    );
}
