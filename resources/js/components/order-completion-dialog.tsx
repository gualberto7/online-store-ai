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
import { complete } from '@/routes/orders';

export default function OrderCompletionDialog({
    teamSlug,
    orderId,
    total,
}: {
    teamSlug: string;
    orderId: number;
    total: string;
}) {
    const [open, setOpen] = useState(false);
    const form = useForm({ payment_method: 'cash', payment_reference: '' });
    const errors = form.errors as Record<string, string>;
    function submit(event: FormEvent) {
        event.preventDefault();
        form.submit(complete({ current_team: teamSlug, order: orderId }), {
            onSuccess: () => {
                setOpen(false);
                form.reset();
            },
        });
    }
    return (
        <>
            <Button
                onClick={() => {
                    form.clearErrors();
                    setOpen(true);
                }}
            >
                Registrar cobro y entrega
            </Button>
            <Dialog
                open={open}
                onOpenChange={(value) => {
                    if (!form.processing) setOpen(value);
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Registrar cobro y entrega</DialogTitle>
                        <DialogDescription>
                            Confirma que recibiste el pago total de {total} y
                            entregaste todos los productos al cliente.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submit} className="grid gap-5">
                        <div className="grid gap-2">
                            <Label htmlFor="payment-method">
                                Método de pago
                            </Label>
                            <select
                                id="payment-method"
                                value={form.data.payment_method}
                                onChange={(event) =>
                                    form.setData(
                                        'payment_method',
                                        event.target.value,
                                    )
                                }
                                className="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                            >
                                <option value="cash">Efectivo</option>
                                <option value="card">Tarjeta</option>
                                <option value="transfer">
                                    Transferencia / QR
                                </option>
                            </select>
                            <InputError message={errors.payment_method} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="payment-reference">
                                Referencia del pago (opcional)
                            </Label>
                            <Input
                                id="payment-reference"
                                value={form.data.payment_reference}
                                onChange={(event) =>
                                    form.setData(
                                        'payment_reference',
                                        event.target.value,
                                    )
                                }
                                maxLength={255}
                                placeholder="Número de operación o comprobante"
                            />
                            <InputError message={errors.payment_reference} />
                        </div>
                        <p className="text-sm text-muted-foreground">
                            Esta acción registra un pago recibido en sucursal.
                            Después de entregar, cualquier devolución se
                            gestiona por separado.
                        </p>
                        <InputError message={errors.order} />
                        <Button type="submit" disabled={form.processing}>
                            {form.processing
                                ? 'Registrando…'
                                : 'Confirmar cobro y entrega'}
                        </Button>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
