import { Form, Head, Link } from '@inertiajs/react';
import { ShoppingBag } from 'lucide-react';
import { home } from '@/routes';
import { destroy, update } from '@/routes/storefront/cart';
import { create } from '@/routes/storefront/checkout';
import { show } from '@/routes/storefront';

export type Cart = {
    items: {
        id: number;
        product_id: number;
        name: string;
        variant: string;
        image: string | null;
        quantity: number;
        unit_price_cents: number;
        subtotal_cents: number;
        available_at: number[];
    }[];
    total_cents: number;
    unavailable: boolean;
};
export const money = (cents: number) =>
    `${(cents / 100).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} Bs`;
export const fieldClass =
    'w-full rounded-xl border border-stone-300 bg-white px-4 py-3 text-stone-900 focus:border-teal-800 focus:outline-none focus:ring-2 focus:ring-teal-800/20';
export const buttonClass =
    'inline-flex items-center justify-center rounded-full bg-teal-900 px-6 py-3 text-sm font-medium text-white hover:bg-teal-800 disabled:cursor-not-allowed disabled:opacity-50';

export default function CartPage({ cart }: { cart: Cart }) {
    return (
        <>
            <Head title="Tu carrito" />
            <h1 className="mb-3 text-3xl font-medium">Tu carrito</h1>
            <p className="mb-8 text-stone-500">
                Revisa tus productos antes de elegir dónde retirarlos.
            </p>
            {cart.unavailable && (
                <div
                    role="alert"
                    className="mb-6 rounded-xl bg-amber-50 p-4 text-amber-900"
                >
                    Algunos productos dejaron de estar disponibles.{' '}
                    <Form {...destroy.form()}>
                        <button className="mt-2 underline">
                            Vaciar carrito
                        </button>
                    </Form>
                </div>
            )}
            {cart.items.length === 0 ? (
                <div className="flex flex-col items-center gap-5 rounded-3xl border border-stone-200 p-12 text-center">
                    <ShoppingBag className="size-12 text-stone-400" />
                    <p>Tu carrito está vacío.</p>
                    <Link href={home()} className={buttonClass}>
                        Explorar el catálogo
                    </Link>
                </div>
            ) : (
                <div className="grid items-start gap-8 lg:grid-cols-[1fr_340px]">
                    <div className="grid gap-4">
                        {cart.items.map((item) => (
                            <article
                                key={item.id}
                                className="flex flex-wrap items-center gap-5 rounded-2xl border border-stone-200 bg-white p-5"
                            >
                                {item.image && (
                                    <img
                                        src={item.image}
                                        alt=""
                                        className="size-20 rounded-xl object-contain"
                                    />
                                )}
                                <div className="min-w-0 flex-1">
                                    <Link
                                        href={show(item.product_id)}
                                        className="font-medium"
                                    >
                                        {item.name}
                                    </Link>
                                    <p className="text-sm text-stone-500">
                                        {item.variant} ·{' '}
                                        {money(item.unit_price_cents)}
                                    </p>
                                    {item.available_at.length === 0 && (
                                        <p className="mt-2 text-sm text-amber-800">
                                            No hay una sucursal con esta
                                            cantidad.
                                        </p>
                                    )}
                                </div>
                                <Form
                                    {...update.form()}
                                    className="flex items-center gap-2"
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            <input
                                                type="hidden"
                                                name="variant_id"
                                                value={item.id}
                                            />
                                            <label
                                                className="sr-only"
                                                htmlFor={`quantity-${item.id}`}
                                            >
                                                Cantidad de {item.name}{' '}
                                                {item.variant}
                                            </label>
                                            <input
                                                id={`quantity-${item.id}`}
                                                key={item.quantity}
                                                name="quantity"
                                                type="number"
                                                min="0"
                                                max="1000"
                                                defaultValue={item.quantity}
                                                className="w-20 rounded-lg border border-stone-300 p-2"
                                            />
                                            <button
                                                disabled={processing}
                                                className="text-sm text-teal-900 underline"
                                            >
                                                Actualizar
                                            </button>
                                            {errors.quantity && (
                                                <p role="alert">
                                                    {errors.quantity}
                                                </p>
                                            )}
                                        </>
                                    )}
                                </Form>
                                <Form {...update.form()}>
                                    <input
                                        type="hidden"
                                        name="variant_id"
                                        value={item.id}
                                    />
                                    <input
                                        type="hidden"
                                        name="quantity"
                                        value="0"
                                    />
                                    <button className="text-sm text-stone-500 underline">
                                        Quitar
                                    </button>
                                </Form>
                                <p className="font-medium">
                                    {money(item.subtotal_cents)}
                                </p>
                            </article>
                        ))}
                    </div>
                    <aside className="grid gap-5 rounded-3xl bg-stone-100 p-7">
                        <h2 className="text-xl font-medium">Resumen</h2>
                        <div className="flex justify-between">
                            <span>Total</span>
                            <strong>{money(cart.total_cents)}</strong>
                        </div>
                        <p className="text-sm text-stone-500">
                            Retiro en sucursal. Pago al retirar. Agregar
                            productos no reserva existencias.
                        </p>
                        {!cart.unavailable && (
                            <Link href={create()} className={buttonClass}>
                                Continuar con la compra
                            </Link>
                        )}
                        <Link
                            href={home()}
                            className="text-center text-sm text-teal-900 underline"
                        >
                            Seguir comprando
                        </Link>
                    </aside>
                </div>
            )}
        </>
    );
}
