import { Head, Link } from '@inertiajs/react';
import { CheckCircle } from 'lucide-react';
import { home } from '@/routes';
import { buttonClass, money } from './cart';
export default function Receipt({
    order,
}: {
    order: {
        id: number;
        status: string;
        customer_name: string;
        total_cents: number;
        branch: { name: string; address: string };
        items: {
            id: number;
            product_name: string;
            variant_name: string;
            quantity: number;
            unit_price_cents: number;
        }[];
    };
}) {
    return (
        <>
            <Head title={`Pedido #${order.id}`} />
            <div className="mx-auto grid max-w-2xl gap-6 rounded-3xl border border-stone-200 bg-white p-7 sm:p-10">
                <CheckCircle className="size-12 text-teal-800" />
                <h1 className="text-3xl font-medium">
                    {order.status === 'cancelled'
                        ? 'Pedido cancelado'
                        : '¡Recibimos tu pedido!'}
                </h1>
                <p>
                    Gracias, {order.customer_name}. Tu número de pedido es{' '}
                    <strong>#{order.id}</strong>.
                </p>
                <div className="rounded-2xl bg-stone-100 p-5">
                    <p className="font-medium">Retiro en {order.branch.name}</p>
                    <p className="text-sm text-stone-500">
                        {order.branch.address}
                    </p>
                    <p className="mt-3 text-sm">
                        {order.status === 'cancelled'
                            ? 'Este pedido fue cancelado.'
                            : 'Pago pendiente · Paga al retirar tu compra.'}
                    </p>
                </div>
                {order.items.map((item) => (
                    <div
                        key={item.id}
                        className="flex justify-between gap-4 text-sm"
                    >
                        <span>
                            {item.quantity} × {item.product_name} ·{' '}
                            {item.variant_name}
                        </span>
                        <span className="shrink-0">
                            {money(item.quantity * item.unit_price_cents)}
                        </span>
                    </div>
                ))}
                <div className="flex justify-between border-t border-stone-200 pt-5 text-xl font-medium">
                    <span>Total</span>
                    <span>{money(order.total_cents)}</span>
                </div>
                <p className="text-sm text-stone-500">
                    Guarda tu número de pedido. Este comprobante está disponible
                    en este navegador mientras tu sesión esté activa.
                </p>
                <Link href={home()} className={buttonClass}>
                    Volver a la tienda
                </Link>
            </div>
        </>
    );
}
