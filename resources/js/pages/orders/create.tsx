import { Head, Link, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index, store } from '@/routes/orders';

type Props = {
    branches: { id: number; name: string }[];
    variants: {
        id: number;
        name: string;
        sku: string;
        price: string;
        product: { name: string };
        stocks: { branch_id: number; quantity: number }[];
    }[];
};

export default function OrderCreate({ branches, variants }: Props) {
    const { currentTeam } = usePage().props;
    const form = useForm({
        branch_id: '',
        customer_name: '',
        notes: '',
        items: [{ variant_id: '', quantity: '1' }],
    });
    if (!currentTeam) return null;
    const selected = form.data.items.map((item) =>
        variants.find((variant) => String(variant.id) === item.variant_id),
    );
    const totalCents = form.data.items.reduce(
        (sum, item, position) =>
            sum +
            Math.round(Number(selected[position]?.price ?? 0) * 100) *
                Number(item.quantity || 0),
        0,
    );
    function updateItem(
        position: number,
        field: 'variant_id' | 'quantity',
        value: string,
    ) {
        form.setData(
            'items',
            form.data.items.map((item, index) =>
                index === position ? { ...item, [field]: value } : item,
            ),
        );
    }
    function submit(event: FormEvent) {
        event.preventDefault();
        form.submit(store(currentTeam!.slug));
    }
    return (
        <>
            <Head title="Nuevo pedido" />
            <div className="flex max-w-4xl flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold">Nuevo pedido</h1>
                    <p className="text-muted-foreground">
                        El borrador conserva los precios actuales. Las
                        existencias se descuentan al confirmar.
                    </p>
                </div>
                {branches.length === 0 || variants.length === 0 ? (
                    <p className="rounded-xl border p-6">
                        Necesitas una sucursal activa y al menos una variante
                        activa para crear pedidos.
                    </p>
                ) : (
                    <form onSubmit={submit} className="space-y-6">
                        <div className="grid gap-2">
                            <Label htmlFor="branch_id">Sucursal</Label>
                            <select
                                id="branch_id"
                                value={form.data.branch_id}
                                onChange={(event) =>
                                    form.setData(
                                        'branch_id',
                                        event.target.value,
                                    )
                                }
                                required
                                className="h-9 rounded-md border border-input bg-background px-3"
                            >
                                <option value="">
                                    Selecciona una sucursal
                                </option>
                                {branches.map((branch) => (
                                    <option key={branch.id} value={branch.id}>
                                        {branch.name}
                                    </option>
                                ))}
                            </select>
                            <InputError message={form.errors.branch_id} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="customer_name">
                                Cliente (opcional)
                            </Label>
                            <Input
                                id="customer_name"
                                value={form.data.customer_name}
                                onChange={(event) =>
                                    form.setData(
                                        'customer_name',
                                        event.target.value,
                                    )
                                }
                                maxLength={255}
                                placeholder="Cliente de mostrador"
                            />
                            <InputError message={form.errors.customer_name} />
                        </div>
                        <section className="flex flex-col gap-4">
                            <h2 className="text-xl font-semibold">Productos</h2>
                            <InputError message={form.errors.items} />
                            {form.data.items.map((item, position) => (
                                <div
                                    key={position}
                                    className="grid gap-3 rounded-xl border p-4"
                                >
                                    <div className="grid gap-2">
                                        <Label htmlFor={`variant-${position}`}>
                                            Variante
                                        </Label>
                                        <select
                                            id={`variant-${position}`}
                                            value={item.variant_id}
                                            onChange={(event) =>
                                                updateItem(
                                                    position,
                                                    'variant_id',
                                                    event.target.value,
                                                )
                                            }
                                            required
                                            className="h-9 min-w-0 rounded-md border border-input bg-background px-3"
                                        >
                                            <option value="">
                                                Selecciona una variante
                                            </option>
                                            {variants
                                                .filter(
                                                    (variant) =>
                                                        String(variant.id) ===
                                                            item.variant_id ||
                                                        !form.data.items.some(
                                                            (line) =>
                                                                line.variant_id ===
                                                                String(
                                                                    variant.id,
                                                                ),
                                                        ),
                                                )
                                                .map((variant) => (
                                                    <option
                                                        key={variant.id}
                                                        value={variant.id}
                                                    >
                                                        {variant.product.name} ·{' '}
                                                        {variant.name} ·{' '}
                                                        {variant.sku}
                                                    </option>
                                                ))}
                                        </select>
                                        <InputError
                                            message={
                                                form.errors[
                                                    `items.${position}.variant_id`
                                                ]
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor={`quantity-${position}`}>
                                            Cantidad
                                        </Label>
                                        <Input
                                            id={`quantity-${position}`}
                                            type="number"
                                            min="1"
                                            max="1000"
                                            step="1"
                                            value={item.quantity}
                                            onChange={(event) =>
                                                updateItem(
                                                    position,
                                                    'quantity',
                                                    event.target.value,
                                                )
                                            }
                                            required
                                        />
                                        <InputError
                                            message={
                                                form.errors[
                                                    `items.${position}.quantity`
                                                ]
                                            }
                                        />
                                    </div>
                                    {selected[position] && (
                                        <p className="text-sm text-muted-foreground">
                                            Precio unitario:{' '}
                                            {selected[position]!.price} Bs ·
                                            Existencias en la sucursal:{' '}
                                            {selected[position]!.stocks.find(
                                                (stock) =>
                                                    String(stock.branch_id) ===
                                                    form.data.branch_id,
                                            )?.quantity ?? 0}
                                        </p>
                                    )}
                                    {form.data.items.length > 1 && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            className="self-start"
                                            onClick={() =>
                                                form.setData(
                                                    'items',
                                                    form.data.items.filter(
                                                        (_, index) =>
                                                            index !== position,
                                                    ),
                                                )
                                            }
                                        >
                                            Quitar línea
                                        </Button>
                                    )}
                                </div>
                            ))}
                            <Button
                                type="button"
                                variant="outline"
                                className="self-start"
                                disabled={
                                    form.data.items.length >=
                                    Math.min(100, variants.length)
                                }
                                onClick={() =>
                                    form.setData('items', [
                                        ...form.data.items,
                                        { variant_id: '', quantity: '1' },
                                    ])
                                }
                            >
                                Agregar producto
                            </Button>
                        </section>
                        <div className="grid gap-2">
                            <Label htmlFor="notes">Notas (opcional)</Label>
                            <textarea
                                id="notes"
                                value={form.data.notes}
                                onChange={(event) =>
                                    form.setData('notes', event.target.value)
                                }
                                maxLength={2000}
                                rows={3}
                                className="rounded-md border border-input bg-background p-3"
                            />
                            <InputError message={form.errors.notes} />
                        </div>
                        <p className="text-xl font-semibold">
                            Total estimado:{' '}
                            {(totalCents / 100).toLocaleString('es-BO', {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2,
                            })}{' '}
                            Bs
                        </p>
                        <div className="flex gap-3">
                            <Button type="submit" disabled={form.processing}>
                                {form.processing
                                    ? 'Creando…'
                                    : 'Crear borrador'}
                            </Button>
                            <Button variant="outline" asChild>
                                <Link href={index(currentTeam.slug)}>
                                    Cancelar
                                </Link>
                            </Button>
                        </div>
                    </form>
                )}
            </div>
        </>
    );
}
OrderCreate.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: props.currentTeam
        ? [{ title: 'Pedidos', href: index(props.currentTeam.slug) }]
        : [],
});
