import { Form, Head, Link, usePage } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index, show } from '@/routes/products';
import { store, update } from '@/routes/products/variants';
import type { Team } from '@/types';

type Props = {
    product: { id: number; name: string };
    variant?: {
        id: number;
        name: string;
        sku: string;
        price: string;
        is_active: boolean;
    };
};

export default function VariantForm({ product, variant }: Props) {
    const { currentTeam } = usePage<{ currentTeam: Team | null }>().props;
    if (!currentTeam) return null;
    const args = { current_team: currentTeam.slug, product: product.id };
    const title = variant ? 'Editar variante' : 'Nueva variante';
    return (
        <>
            <Head title={title} />
            <div className="flex max-w-2xl flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold">{title}</h1>
                    <p className="text-muted-foreground">{product.name}</p>
                </div>
                <Form
                    key={variant?.id ?? 'create'}
                    {...(variant
                        ? update.form({ ...args, variant: variant.id })
                        : store.form(args))}
                    className="space-y-6"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">
                                    Nombre de la variante
                                </Label>
                                <Input
                                    id="name"
                                    name="name"
                                    defaultValue={variant?.name}
                                    placeholder="Negro / talla 42 o Estándar"
                                    maxLength={255}
                                    required
                                    aria-invalid={!!errors.name}
                                />
                                <InputError message={errors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="sku">SKU</Label>
                                <Input
                                    id="sku"
                                    name="sku"
                                    defaultValue={variant?.sku}
                                    placeholder="ZAP-NEG-42"
                                    maxLength={100}
                                    required
                                    aria-invalid={!!errors.sku}
                                />
                                <p className="text-sm text-muted-foreground">
                                    Código único del negocio. Usa letras,
                                    números, puntos, guiones o guiones bajos.
                                </p>
                                <InputError message={errors.sku} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="price">Precio (Bs)</Label>
                                <Input
                                    id="price"
                                    name="price"
                                    type="number"
                                    inputMode="decimal"
                                    min="0"
                                    max="9999999999.99"
                                    step="0.01"
                                    defaultValue={variant?.price}
                                    required
                                    aria-invalid={!!errors.price}
                                />
                                <InputError message={errors.price} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="is_active">Estado</Label>
                                <select
                                    id="is_active"
                                    name="is_active"
                                    defaultValue={
                                        variant?.is_active === false ? '0' : '1'
                                    }
                                    className="h-9 rounded-md border border-input bg-background px-3 text-sm focus-visible:ring-2 focus-visible:ring-ring"
                                >
                                    <option value="1">Activa</option>
                                    <option value="0">Inactiva</option>
                                </select>
                                <InputError message={errors.is_active} />
                            </div>
                            <div className="flex gap-3">
                                <Button type="submit" disabled={processing}>
                                    {processing
                                        ? 'Guardando…'
                                        : 'Guardar variante'}
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link href={show(args)}>Cancelar</Link>
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
VariantForm.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: props.currentTeam
        ? [{ title: 'Productos', href: index(props.currentTeam.slug) }]
        : [],
});
