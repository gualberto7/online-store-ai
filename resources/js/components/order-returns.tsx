import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store } from '@/routes/orders/returns';

type Item = {
    id: number;
    product_name: string;
    variant_name: string;
    quantity: number;
    unit_price_cents: number;
};
export type OrderReturn = {
    id: number;
    order_item_id: number;
    quantity: number;
    restock: boolean;
    amount_cents: number;
    reason: string;
    payment_method: 'cash' | 'card' | 'transfer';
    payment_reference: string | null;
    created_at: string;
    user: { name: string } | null;
};
const money = (cents: number) =>
    `${(cents / 100).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} Bs`;
const methods = {
    cash: 'Efectivo',
    card: 'Tarjeta',
    transfer: 'Transferencia / QR',
};
export default function OrderReturns({
    teamSlug,
    orderId,
    items,
    returns,
    canManage,
}: {
    teamSlug: string;
    orderId: number;
    items: Item[];
    returns: OrderReturn[];
    canManage: boolean;
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({
        order_item_id: '',
        quantity: 1,
        restock: false,
        reason: '',
        payment_method: 'cash',
        payment_reference: '',
        request_token: '',
    });
    const errors = form.errors as Record<string, string>;
    const available = items
        .map((item) => ({
            ...item,
            remaining:
                item.quantity -
                returns
                    .filter((entry) => entry.order_item_id === item.id)
                    .reduce((sum, entry) => sum + entry.quantity, 0),
        }))
        .filter((item) => item.remaining > 0);
    const selected = available.find(
        (item) => String(item.id) === form.data.order_item_id,
    );
    const refunded = returns.reduce(
        (sum, entry) => sum + entry.amount_cents,
        0,
    );
    function startReturn() {
        form.clearErrors();
        form.setData({
            order_item_id: String(available[0]?.id ?? ''),
            quantity: 1,
            restock: false,
            reason: '',
            payment_method: 'cash',
            payment_reference: '',
            request_token: crypto.randomUUID(),
        });
        setOpen(true);
    }
    function submit(event: FormEvent) {
        event.preventDefault();
        form.submit(store({ current_team: teamSlug, order: orderId }), {
            onSuccess: () => setOpen(false),
        });
    }
    return (
        <section className="grid gap-4 rounded-xl border p-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 className="font-semibold">Devoluciones</h2>
                    <p className="text-sm text-muted-foreground">
                        Reintegrado: {money(refunded)}
                    </p>
                </div>
                {canManage && available.length > 0 && (
                    <Button variant="outline" onClick={startReturn}>
                        Registrar devolución
                    </Button>
                )}
            </div>
            {returns.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    Este pedido todavía no tiene devoluciones.
                </p>
            ) : (
                returns.map((entry) => (
                    <article
                        key={entry.id}
                        className="grid gap-2 rounded-lg bg-muted/50 p-4 text-sm"
                    >
                        <div className="flex flex-wrap justify-between gap-3">
                            <strong>Devolución #{entry.id}</strong>
                            <strong>{money(entry.amount_cents)}</strong>
                        </div>
                        <p>
                            {entry.quantity} ×{' '}
                            {
                                items.find(
                                    (item) => item.id === entry.order_item_id,
                                )?.product_name
                            }{' '}
                            ·{' '}
                            {
                                items.find(
                                    (item) => item.id === entry.order_item_id,
                                )?.variant_name
                            }
                        </p>
                        <p>
                            {entry.restock
                                ? 'Unidades devueltas al inventario de esta sucursal'
                                : 'Unidades no reincorporadas al inventario'}
                        </p>
                        <p className="wrap-break-word">
                            Motivo: {entry.reason}
                        </p>
                        <p>Reintegro: {methods[entry.payment_method]}</p>
                        {entry.payment_reference && (
                            <p className="wrap-break-word">
                                Referencia: {entry.payment_reference}
                            </p>
                        )}
                        <p className="text-muted-foreground">
                            {new Date(entry.created_at).toLocaleString('es-BO')}{' '}
                            · {entry.user?.name ?? 'Usuario eliminado'}
                        </p>
                    </article>
                ))
            )}
            {available.length === 0 && (
                <p className="text-sm">
                    Todas las unidades del pedido fueron devueltas.
                </p>
            )}
            <Dialog
                open={open}
                onOpenChange={(value) => {
                    if (!form.processing) setOpen(value);
                }}
            >
                <DialogContent className="max-h-[90dvh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>Registrar devolución</DialogTitle>
                        <DialogDescription>
                            Selecciona una variante y las unidades recibidas. El
                            reintegro usa el precio original de la venta.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submit} className="grid gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="return-item">
                                Producto y variante
                            </Label>
                            <select
                                id="return-item"
                                className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                                value={form.data.order_item_id}
                                onChange={(event) => {
                                    form.setData(
                                        'order_item_id',
                                        event.target.value,
                                    );
                                    form.setData('quantity', 1);
                                }}
                            >
                                {available.map((item) => (
                                    <option key={item.id} value={item.id}>
                                        {item.product_name} ·{' '}
                                        {item.variant_name} ({item.remaining}{' '}
                                        disponibles)
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="return-quantity">
                                Unidades a devolver
                            </Label>
                            <Input
                                id="return-quantity"
                                required
                                type="number"
                                min="1"
                                max={selected?.remaining ?? 0}
                                value={form.data.quantity}
                                onChange={(event) =>
                                    form.setData(
                                        'quantity',
                                        Number(event.target.value),
                                    )
                                }
                            />
                        </div>
                        <label className="flex items-start gap-3 text-sm">
                            <input
                                type="checkbox"
                                checked={form.data.restock}
                                onChange={(event) =>
                                    form.setData(
                                        'restock',
                                        event.target.checked,
                                    )
                                }
                                className="mt-1 size-4 accent-primary"
                            />
                            <span>
                                Reincorporar al inventario de la sucursal
                                original
                                <span className="block text-muted-foreground">
                                    Marca esta opción únicamente si las unidades
                                    pueden volver a venderse.
                                </span>
                            </span>
                        </label>
                        <div className="grid gap-2">
                            <Label htmlFor="return-reason">Motivo</Label>
                            <Input
                                id="return-reason"
                                required
                                maxLength={500}
                                value={form.data.reason}
                                onChange={(event) =>
                                    form.setData('reason', event.target.value)
                                }
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="return-method">
                                Método del reintegro realizado
                            </Label>
                            <select
                                id="return-method"
                                className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                                value={form.data.payment_method}
                                onChange={(event) =>
                                    form.setData(
                                        'payment_method',
                                        event.target.value,
                                    )
                                }
                            >
                                {Object.entries(methods).map(
                                    ([value, label]) => (
                                        <option key={value} value={value}>
                                            {label}
                                        </option>
                                    ),
                                )}
                            </select>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="return-reference">
                                Referencia (opcional)
                            </Label>
                            <Input
                                id="return-reference"
                                maxLength={255}
                                value={form.data.payment_reference}
                                onChange={(event) =>
                                    form.setData(
                                        'payment_reference',
                                        event.target.value,
                                    )
                                }
                            />
                        </div>
                        <p className="text-lg font-semibold">
                            Reintegro:{' '}
                            {money(
                                (selected?.unit_price_cents ?? 0) *
                                    form.data.quantity,
                            )}
                        </p>
                        <p className="text-sm text-muted-foreground">
                            Confirma que recibiste estas unidades y ya
                            reintegraste el importe al cliente. El sistema
                            registra el reintegro realizado en sucursal.
                        </p>
                        {Object.entries(errors).map(([key, message]) => (
                            <InputError key={key} message={message} />
                        ))}
                        <Button
                            type="submit"
                            disabled={form.processing || !selected}
                        >
                            {form.processing
                                ? 'Registrando…'
                                : 'Confirmar devolución y reintegro'}
                        </Button>
                    </form>
                </DialogContent>
            </Dialog>
        </section>
    );
}
