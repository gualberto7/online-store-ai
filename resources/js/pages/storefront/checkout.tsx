import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { index } from '@/routes/storefront/cart';
import { store } from '@/routes/storefront/checkout';
import { buttonClass, fieldClass, money } from './cart';
import type { Cart } from './cart';
export default function Checkout({
    cart,
    branches,
    token,
}: {
    cart: Cart;
    branches: { id: number; name: string; address: string }[];
    token: string;
}) {
    const form = useForm({
        customer_name: '',
        customer_email: '',
        customer_phone: '',
        branch_id: '',
        notes: '',
        token,
    });
    const availableBranches = branches.filter((branch) =>
        cart.items.every((item) => item.available_at.includes(branch.id)),
    );
    const errors = form.errors as Record<string, string>;
    function submit(event: FormEvent) {
        event.preventDefault();
        form.submit(store());
    }
    return (
        <>
            <Head title="Finalizar compra" />
            <Link href={index()} className="text-sm text-teal-900 underline">
                Volver al carrito
            </Link>
            <h1 className="mt-5 mb-8 text-3xl font-medium">Finalizar compra</h1>
            <div className="grid items-start gap-10 lg:grid-cols-[1fr_380px]">
                <form onSubmit={submit} className="grid gap-6">
                    <h2 className="text-xl font-medium">Tus datos</h2>
                    {(
                        [
                            'customer_name',
                            'customer_email',
                            'customer_phone',
                        ] as const
                    ).map((key) => (
                        <div key={key} className="grid gap-2">
                            <label htmlFor={key}>
                                {
                                    {
                                        customer_name: 'Nombre completo',
                                        customer_email: 'Correo electrónico',
                                        customer_phone: 'Teléfono',
                                    }[key]
                                }
                            </label>
                            <input
                                id={key}
                                type={
                                    key === 'customer_email'
                                        ? 'email'
                                        : key === 'customer_phone'
                                          ? 'tel'
                                          : 'text'
                                }
                                autoComplete={
                                    key === 'customer_name'
                                        ? 'name'
                                        : key === 'customer_email'
                                          ? 'email'
                                          : 'tel'
                                }
                                required
                                maxLength={key === 'customer_phone' ? 40 : 255}
                                className={fieldClass}
                                value={form.data[key]}
                                onChange={(e) =>
                                    form.setData(key, e.target.value)
                                }
                            />
                            {errors[key] && (
                                <p className="text-sm text-red-700">
                                    {errors[key]}
                                </p>
                            )}
                        </div>
                    ))}
                    <div className="grid gap-2">
                        <label htmlFor="branch_id">Sucursal de retiro</label>
                        <select
                            id="branch_id"
                            required
                            className={fieldClass}
                            value={form.data.branch_id}
                            onChange={(e) =>
                                form.setData('branch_id', e.target.value)
                            }
                        >
                            <option value="">Selecciona una sucursal</option>
                            {availableBranches.map((branch) => (
                                <option key={branch.id} value={branch.id}>
                                    {branch.name} · {branch.address}
                                </option>
                            ))}
                        </select>
                        <p className="text-sm text-stone-500">
                            Mostramos las sucursales con existencias para todo
                            tu carrito.
                        </p>
                    </div>
                    <div className="grid gap-2">
                        <label htmlFor="notes">Notas (opcional)</label>
                        <textarea
                            id="notes"
                            maxLength={2000}
                            className={fieldClass}
                            value={form.data.notes}
                            onChange={(e) =>
                                form.setData('notes', e.target.value)
                            }
                        />
                    </div>
                    {Object.entries(errors).map(([key, error]) => (
                        <p
                            key={key}
                            role="alert"
                            className="text-sm text-red-700"
                        >
                            {error}
                        </p>
                    ))}
                    {(availableBranches.length === 0 || cart.unavailable) && (
                        <p
                            role="alert"
                            className="rounded-xl bg-amber-50 p-4 text-amber-900"
                        >
                            Revisa el carrito: no hay una sucursal disponible
                            para completar esta compra.
                        </p>
                    )}
                    <button
                        disabled={
                            form.processing ||
                            cart.unavailable ||
                            !availableBranches.length
                        }
                        className={buttonClass}
                    >
                        {form.processing
                            ? 'Confirmando…'
                            : 'Confirmar pedido · Pago al retirar'}
                    </button>
                </form>
                <aside className="grid gap-5 rounded-3xl bg-stone-100 p-7">
                    <h2 className="text-xl font-medium">Tu pedido</h2>
                    {cart.items.map((item) => (
                        <div
                            key={item.id}
                            className="flex justify-between gap-4 text-sm"
                        >
                            <span>
                                {item.quantity} × {item.name}
                                <span className="block text-stone-500">
                                    {item.variant}
                                </span>
                            </span>
                            <span className="shrink-0">
                                {money(item.subtotal_cents)}
                            </span>
                        </div>
                    ))}
                    <div className="flex justify-between border-t border-stone-300 pt-5 text-lg font-medium">
                        <span>Total</span>
                        <span>{money(cart.total_cents)}</span>
                    </div>
                    <p className="text-sm text-stone-500">
                        El pedido se confirma con los precios y existencias
                        vigentes. Pagarás al retirar en la sucursal elegida.
                    </p>
                </aside>
            </div>
        </>
    );
}
