import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import OrderCompletionDialog from '@/components/order-completion-dialog';
import OrderReturns from '@/components/order-returns';
import type { OrderReturn } from '@/components/order-returns';
import { Badge } from '@/components/ui/badge';
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
import { cancel, confirm, index } from '@/routes/orders';

type Props = {
    order: {
        id: number;
        status: 'draft' | 'confirmed' | 'cancelled';
        customer_name: string | null;
        customer_email: string | null;
        customer_phone: string | null;
        checkout_token: string | null;
        notes: string | null;
        total_cents: number;
        created_at: string;
        confirmed_at: string | null;
        completed_at: string | null;
        completed_by: { name: string } | null;
        payment_method: 'cash' | 'card' | 'transfer' | null;
        payment_reference: string | null;
        cancelled_at: string | null;
        cancellation_reason: string | null;
        branch: { name: string };
        user: { name: string } | null;
        returns: OrderReturn[];
        items: {
            id: number;
            product_name: string;
            variant_name: string;
            sku: string;
            quantity: number;
            unit_price_cents: number;
        }[];
    };
    canManage: boolean;
};
const labels = {
    draft: 'Borrador',
    confirmed: 'Confirmado',
    cancelled: 'Cancelado',
};
const money = (cents: number) =>
    `${(cents / 100).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} Bs`;

export default function OrderShow({ order, canManage }: Props) {
    const { currentTeam } = usePage().props;
    const confirmation = useForm({});
    const cancellation = useForm({ reason: '' });
    const [dialog, setDialog] = useState<'confirm' | 'cancel' | null>(null);
    if (!currentTeam) return null;
    const args = { current_team: currentTeam.slug, order: order.id };
    const errors = confirmation.errors as Record<string, string>;
    const cancelErrors = cancellation.errors as Record<string, string>;
    function submit(event: FormEvent) {
        event.preventDefault();
        if (dialog === 'confirm')
            confirmation.submit(confirm(args), {
                onSuccess: () => setDialog(null),
            });
        else
            cancellation.submit(cancel(args), {
                onSuccess: () => setDialog(null),
            });
    }
    return (
        <>
            <Head title={`Pedido #${order.id}`} />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <h1 className="text-2xl font-semibold">
                        Pedido #{order.id}
                    </h1>
                    <Badge
                        variant={
                            order.status === 'confirmed'
                                ? 'default'
                                : 'secondary'
                        }
                    >
                        {order.completed_at
                            ? 'Entregado'
                            : labels[order.status]}
                    </Badge>
                </div>
                <div className="grid gap-2">
                    <p>Sucursal: {order.branch.name}</p>
                    <p>
                        Cliente: {order.customer_name || 'Cliente de mostrador'}
                    </p>
                    <p className="text-sm text-muted-foreground">
                        Creado por{' '}
                        {order.checkout_token
                            ? 'Tienda en línea'
                            : (order.user?.name ?? 'Usuario eliminado')}{' '}
                        · {new Date(order.created_at).toLocaleString('es-BO')}
                    </p>
                    {order.checkout_token && (
                        <>
                            <p>Correo: {order.customer_email}</p>
                            <p>Teléfono: {order.customer_phone}</p>
                            <p>Retiro en sucursal · Pago al retirar</p>
                        </>
                    )}
                    {order.confirmed_at && (
                        <p className="text-sm">
                            Confirmado:{' '}
                            {new Date(order.confirmed_at).toLocaleString(
                                'es-BO',
                            )}
                        </p>
                    )}
                    {order.cancelled_at && (
                        <p className="text-sm">
                            Cancelado:{' '}
                            {new Date(order.cancelled_at).toLocaleString(
                                'es-BO',
                            )}{' '}
                            · {order.cancellation_reason}
                        </p>
                    )}
                    {order.notes && (
                        <p className="wrap-break-word whitespace-pre-wrap">
                            {order.notes}
                        </p>
                    )}
                </div>
                {order.completed_at ? (
                    <section className="grid gap-2 rounded-xl border p-4">
                        <h2 className="font-medium">Pagado y entregado</h2>
                        <p>
                            {new Date(order.completed_at).toLocaleString(
                                'es-BO',
                            )}{' '}
                            · {order.completed_by?.name ?? 'Usuario eliminado'}
                        </p>
                        <p>
                            Método:{' '}
                            {order.payment_method
                                ? {
                                      cash: 'Efectivo',
                                      card: 'Tarjeta',
                                      transfer: 'Transferencia / QR',
                                  }[order.payment_method]
                                : '—'}
                        </p>
                        {order.payment_reference && (
                            <p className="wrap-break-word">
                                Referencia: {order.payment_reference}
                            </p>
                        )}
                    </section>
                ) : (
                    order.status === 'confirmed' && (
                        <p className="text-sm text-muted-foreground">
                            Pago pendiente · Pendiente de entrega
                        </p>
                    )
                )}
                <div className="grid gap-3">
                    {order.items.map((item) => (
                        <article
                            key={item.id}
                            className="flex flex-wrap items-center justify-between gap-4 rounded-xl border p-4"
                        >
                            <div>
                                <h2 className="font-medium">
                                    {item.product_name} · {item.variant_name}
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    SKU {item.sku} · {item.quantity} ×{' '}
                                    {money(item.unit_price_cents)}
                                </p>
                            </div>
                            <p className="font-semibold">
                                {money(item.quantity * item.unit_price_cents)}
                            </p>
                        </article>
                    ))}
                </div>
                <p className="text-2xl font-semibold">
                    Total: {money(order.total_cents)}
                </p>
                {order.completed_at && (
                    <OrderReturns
                        teamSlug={currentTeam.slug}
                        orderId={order.id}
                        items={order.items}
                        returns={order.returns}
                        canManage={canManage}
                    />
                )}
                <InputError
                    message={
                        errors.order ||
                        errors.quantity ||
                        cancelErrors.order ||
                        cancelErrors.quantity
                    }
                />
                {canManage &&
                    order.status !== 'cancelled' &&
                    !order.completed_at && (
                        <div className="flex flex-wrap gap-3">
                            {order.status === 'draft' && (
                                <Button
                                    onClick={() => {
                                        confirmation.clearErrors();
                                        setDialog('confirm');
                                    }}
                                >
                                    Confirmar venta
                                </Button>
                            )}
                            {order.status === 'confirmed' && (
                                <OrderCompletionDialog
                                    teamSlug={currentTeam.slug}
                                    orderId={order.id}
                                    total={money(order.total_cents)}
                                />
                            )}
                            <Button
                                variant="destructive"
                                onClick={() => {
                                    cancellation.clearErrors();
                                    setDialog('cancel');
                                }}
                            >
                                Cancelar pedido
                            </Button>
                        </div>
                    )}
                <Button variant="outline" className="self-start" asChild>
                    <Link href={index(currentTeam.slug)}>Volver a pedidos</Link>
                </Button>
                <Dialog
                    open={dialog !== null}
                    onOpenChange={(open) => {
                        if (
                            !open &&
                            !confirmation.processing &&
                            !cancellation.processing
                        )
                            setDialog(null);
                    }}
                >
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>
                                {dialog === 'confirm'
                                    ? 'Confirmar venta'
                                    : 'Cancelar pedido'}
                            </DialogTitle>
                            <DialogDescription>
                                {dialog === 'confirm'
                                    ? 'Se descontarán todas las unidades del inventario de esta sucursal.'
                                    : order.status === 'confirmed'
                                      ? 'Se devolverán las unidades al inventario de esta sucursal.'
                                      : 'Se cancelará el borrador. El inventario no cambia.'}
                            </DialogDescription>
                        </DialogHeader>
                        <form onSubmit={submit} className="space-y-4">
                            {dialog === 'cancel' && (
                                <div className="grid gap-2">
                                    <Label htmlFor="reason">
                                        Motivo de cancelación
                                    </Label>
                                    <Input
                                        id="reason"
                                        value={cancellation.data.reason}
                                        onChange={(event) =>
                                            cancellation.setData(
                                                'reason',
                                                event.target.value,
                                            )
                                        }
                                        maxLength={500}
                                        required
                                    />
                                    <InputError
                                        message={cancellation.errors.reason}
                                    />
                                </div>
                            )}
                            <InputError
                                message={
                                    dialog === 'confirm'
                                        ? errors.order || errors.quantity
                                        : cancelErrors.order ||
                                          cancelErrors.quantity
                                }
                            />
                            <Button
                                type="submit"
                                disabled={
                                    confirmation.processing ||
                                    cancellation.processing
                                }
                            >
                                {confirmation.processing ||
                                cancellation.processing
                                    ? 'Procesando…'
                                    : dialog === 'confirm'
                                      ? 'Confirmar venta'
                                      : 'Cancelar pedido'}
                            </Button>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>
        </>
    );
}
OrderShow.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: props.currentTeam
        ? [{ title: 'Pedidos', href: index(props.currentTeam.slug) }]
        : [],
});
