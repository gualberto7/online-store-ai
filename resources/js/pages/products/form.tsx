import { Form, Head, Link, usePage } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Team } from '@/types';
import { index, store, update } from '@/routes/products';

type Product = {
    id: number;
    name: string;
    description: string | null;
    is_active: boolean;
    category_id: number | null;
};

type Category = { id: number; name: string; is_active: boolean };

export default function ProductForm({
    product,
    categories,
}: {
    product?: Product;
    categories: Category[];
}) {
    const { currentTeam } = usePage<{ currentTeam: Team | null }>().props;
    if (!currentTeam) return null;
    const title = product ? 'Editar producto' : 'Nuevo producto';

    return (
        <>
            <Head title={title} />
            <div className="flex max-w-2xl flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold">{title}</h1>
                    <p className="text-muted-foreground">
                        Completa los datos del producto compartido entre tus
                        sucursales.
                    </p>
                </div>
                <Form
                    key={product?.id ?? 'create'}
                    {...(product
                        ? update.form({
                              current_team: currentTeam.slug,
                              product: product.id,
                          })
                        : store.form(currentTeam.slug))}
                    className="space-y-6"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">Nombre</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    defaultValue={product?.name}
                                    placeholder="Zapatillas Urbanas"
                                    maxLength={255}
                                    required
                                    aria-invalid={!!errors.name}
                                />
                                <InputError message={errors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="description">Descripción</Label>
                                <textarea
                                    id="description"
                                    name="description"
                                    defaultValue={product?.description ?? ''}
                                    maxLength={10000}
                                    rows={5}
                                    className="rounded-md border border-input bg-background p-3 text-sm focus-visible:ring-2 focus-visible:ring-ring"
                                    aria-invalid={!!errors.description}
                                />
                                <InputError message={errors.description} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="category_id">Categoría</Label>
                                <select
                                    id="category_id"
                                    name="category_id"
                                    defaultValue={product?.category_id ?? ''}
                                    className="h-9 rounded-md border border-input bg-background px-3 text-sm focus-visible:ring-2 focus-visible:ring-ring"
                                    aria-invalid={!!errors.category_id}
                                >
                                    <option value="">Sin categoría</option>
                                    {categories.map((category) => (
                                        <option
                                            key={category.id}
                                            value={category.id}
                                        >
                                            {category.name}
                                            {!category.is_active
                                                ? ' (inactiva)'
                                                : ''}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.category_id} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="is_active">Estado</Label>
                                <select
                                    id="is_active"
                                    name="is_active"
                                    defaultValue={
                                        product?.is_active === false ? '0' : '1'
                                    }
                                    className="h-9 rounded-md border border-input bg-background px-3 text-sm focus-visible:ring-2 focus-visible:ring-ring"
                                    aria-invalid={!!errors.is_active}
                                >
                                    <option value="1">Activo</option>
                                    <option value="0">Inactivo</option>
                                </select>
                                <p className="text-sm text-muted-foreground">
                                    Puedes desactivar un producto sin borrar su
                                    información.
                                </p>
                                <InputError message={errors.is_active} />
                            </div>
                            <div className="flex gap-3">
                                <Button type="submit" disabled={processing}>
                                    {processing
                                        ? 'Guardando…'
                                        : 'Guardar producto'}
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link href={index(currentTeam.slug)}>
                                        Cancelar
                                    </Link>
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

ProductForm.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: props.currentTeam
        ? [{ title: 'Productos', href: index(props.currentTeam.slug) }]
        : [],
});
