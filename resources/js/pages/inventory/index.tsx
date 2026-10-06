import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
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
import { index as branchesIndex } from '@/routes/branches';
import { index, store } from '@/routes/branches/inventory';
import type { Team } from '@/types';

type Variant = {
    id: number;
    name: string;
    sku: string;
    is_active: boolean;
    product: { name: string; is_active: boolean };
    stocks: { quantity: number }[];
};
type Movement = {
    id: number;
    type: string;
    quantity_change: number;
    balance_before: number;
    balance_after: number;
    reason: string;
    created_at: string;
    user: { name: string } | null;
    stock: {
        variant: { name: string; sku: string; product: { name: string } };
    };
};
type Props = {
    branch: { id: number; name: string; is_active: boolean };
    variants: { data: Variant[]; current_page: number; last_page: number };
    movements: Movement[];
    canManage: boolean;
};
const movementLabels: Record<string, string> = {
    receipt: 'Entrada',
    issue: 'Salida',
    adjustment: 'Ajuste',
};

export default function InventoryIndex({
    branch,
    variants,
    movements,
    canManage,
}: Props) {
    const { currentTeam } = usePage<{ currentTeam: Team | null }>().props;
    const [selectedVariant, setSelectedVariant] = useState<Variant | null>(
        null,
    );
    const form = useForm({
        variant_id: '',
        type: 'receipt',
        quantity: '',
        reason: '',
    });
    if (!currentTeam) return null;
    const args = { current_team: currentTeam.slug, branch: branch.id };

    function selectVariant(variant: Variant) {
        setSelectedVariant(variant);
        form.setData({
            variant_id: String(variant.id),
            type: 'receipt',
            quantity: '',
            reason: '',
        });
        form.clearErrors();
    }
    function submit(event: FormEvent) {
        event.preventDefault();
        form.submit(store(args), {
            preserveScroll: true,
            onSuccess: () => {
                setSelectedVariant(null);
                form.reset();
            },
        });
    }

    return (
        <>
            <Head title={`Inventario · ${branch.name}`} />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            Inventario · {branch.name}
                        </h1>
                        <p className="text-muted-foreground">
                            Existencias por variante en esta sucursal.
                        </p>
                    </div>
                    <Badge variant={branch.is_active ? 'default' : 'secondary'}>
                        {branch.is_active
                            ? 'Sucursal activa'
                            : 'Sucursal inactiva'}
                    </Badge>
                </div>
                {variants.data.length === 0 ? (
                    <p className="rounded-xl border border-dashed p-8 text-center text-muted-foreground">
                        Agrega productos y variantes al catálogo para registrar
                        existencias.
                    </p>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {variants.data.map((variant) => (
                            <article
                                key={variant.id}
                                className="flex flex-col gap-3 rounded-xl border p-5"
                            >
                                <h2 className="font-semibold wrap-break-word">
                                    {variant.product.name}
                                </h2>
                                <p className="text-sm wrap-break-word">
                                    {variant.name} · {variant.sku}
                                </p>
                                {(!variant.is_active ||
                                    !variant.product.is_active) && (
                                    <Badge
                                        variant="secondary"
                                        className="self-start"
                                    >
                                        Producto o variante inactiva
                                    </Badge>
                                )}
                                <p className="text-2xl font-semibold">
                                    {variant.stocks[0]?.quantity ?? 0}
                                    <span className="ml-2 text-sm font-normal text-muted-foreground">
                                        unidades
                                    </span>
                                </p>
                                {canManage && (
                                    <Button
                                        variant="outline"
                                        className="self-start"
                                        onClick={() => selectVariant(variant)}
                                    >
                                        Registrar movimiento
                                    </Button>
                                )}
                            </article>
                        ))}
                    </div>
                )}
                {variants.last_page > 1 && (
                    <nav
                        aria-label="Páginas del inventario"
                        className="flex items-center gap-3"
                    >
                        {variants.current_page > 1 && (
                            <Button variant="outline" asChild>
                                <Link
                                    href={index(args, {
                                        query: {
                                            page: variants.current_page - 1,
                                        },
                                    })}
                                >
                                    Anterior
                                </Link>
                            </Button>
                        )}
                        <span className="text-sm">
                            Página {variants.current_page} de{' '}
                            {variants.last_page}
                        </span>
                        {variants.current_page < variants.last_page && (
                            <Button variant="outline" asChild>
                                <Link
                                    href={index(args, {
                                        query: {
                                            page: variants.current_page + 1,
                                        },
                                    })}
                                >
                                    Siguiente
                                </Link>
                            </Button>
                        )}
                    </nav>
                )}
                {canManage && selectedVariant && (
                    <Dialog
                        open={!!selectedVariant}
                        onOpenChange={(open) => {
                            if (!open && !form.processing)
                                setSelectedVariant(null);
                        }}
                    >
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>Registrar movimiento</DialogTitle>
                                <DialogDescription>
                                    {selectedVariant.product.name} ·{' '}
                                    {selectedVariant.name} ·{' '}
                                    {selectedVariant.sku}
                                </DialogDescription>
                            </DialogHeader>
                            <form onSubmit={submit} className="space-y-4">
                                <InputError message={form.errors.variant_id} />
                                <div className="grid gap-2">
                                    <Label htmlFor="type">Tipo</Label>
                                    <select
                                        id="type"
                                        value={form.data.type}
                                        onChange={(event) =>
                                            form.setData(
                                                'type',
                                                event.target.value,
                                            )
                                        }
                                        className="h-9 rounded-md border border-input bg-background px-3 text-sm focus-visible:ring-2 focus-visible:ring-ring"
                                    >
                                        <option value="receipt">Entrada</option>
                                        <option value="issue">Salida</option>
                                        <option value="adjustment">
                                            Ajuste por conteo
                                        </option>
                                    </select>
                                    <InputError message={form.errors.type} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="quantity">
                                        {form.data.type === 'adjustment'
                                            ? 'Existencias contadas (nuevo saldo)'
                                            : 'Cantidad de unidades'}
                                    </Label>
                                    <Input
                                        id="quantity"
                                        type="number"
                                        min={
                                            form.data.type === 'adjustment'
                                                ? 0
                                                : 1
                                        }
                                        max={2147483647}
                                        step="1"
                                        value={form.data.quantity}
                                        onChange={(event) =>
                                            form.setData(
                                                'quantity',
                                                event.target.value,
                                            )
                                        }
                                        required
                                        aria-invalid={!!form.errors.quantity}
                                    />
                                    <InputError
                                        message={form.errors.quantity}
                                    />
                                    {form.data.type === 'adjustment' && (
                                        <p className="text-sm text-muted-foreground">
                                            El ajuste establece el saldo total
                                            observado en el conteo físico.
                                        </p>
                                    )}
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="reason">Motivo</Label>
                                    <Input
                                        id="reason"
                                        value={form.data.reason}
                                        onChange={(event) =>
                                            form.setData(
                                                'reason',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="Compra, venta, merma o conteo físico"
                                        maxLength={500}
                                        required
                                        aria-invalid={!!form.errors.reason}
                                    />
                                    <InputError message={form.errors.reason} />
                                </div>
                                <div className="flex gap-3">
                                    <Button
                                        type="submit"
                                        disabled={form.processing}
                                    >
                                        {form.processing
                                            ? 'Registrando…'
                                            : 'Guardar movimiento'}
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={form.processing}
                                        onClick={() => setSelectedVariant(null)}
                                    >
                                        Cancelar
                                    </Button>
                                </div>
                            </form>
                        </DialogContent>
                    </Dialog>
                )}
                <section className="flex flex-col gap-4">
                    <h2 className="text-xl font-semibold">
                        Últimos 20 movimientos
                    </h2>
                    {movements.length === 0 ? (
                        <p className="text-muted-foreground">
                            Todavía no hay movimientos en esta sucursal.
                        </p>
                    ) : (
                        <div className="flex flex-col gap-3">
                            {movements.map((movement) => (
                                <article
                                    key={movement.id}
                                    className="flex flex-col gap-2 rounded-xl border p-4"
                                >
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <h3 className="font-medium">
                                            {
                                                movement.stock.variant.product
                                                    .name
                                            }{' '}
                                            · {movement.stock.variant.name}
                                        </h3>
                                        <Badge variant="secondary">
                                            {movementLabels[movement.type]}:{' '}
                                            {movement.quantity_change > 0
                                                ? '+'
                                                : ''}
                                            {movement.quantity_change}
                                        </Badge>
                                    </div>
                                    <p className="text-sm text-muted-foreground">
                                        SKU {movement.stock.variant.sku} ·
                                        Saldo: {movement.balance_before} →{' '}
                                        {movement.balance_after}
                                    </p>
                                    <p className="text-sm wrap-break-word">
                                        {movement.reason}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {movement.user?.name ??
                                            'Usuario eliminado'}{' '}
                                        ·{' '}
                                        {new Date(
                                            movement.created_at,
                                        ).toLocaleString('es-BO')}
                                    </p>
                                </article>
                            ))}
                        </div>
                    )}
                </section>
                <Button variant="outline" className="self-start" asChild>
                    <Link href={branchesIndex(currentTeam.slug)}>
                        Volver a sucursales
                    </Link>
                </Button>
            </div>
        </>
    );
}
InventoryIndex.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: props.currentTeam
        ? [{ title: 'Sucursales', href: branchesIndex(props.currentTeam.slug) }]
        : [],
});
