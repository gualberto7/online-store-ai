import { Head, Link, usePage } from '@inertiajs/react';
import ProductImages from '@/components/product-images';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { edit, index } from '@/routes/products';
import {
    create as createVariant,
    edit as editVariant,
} from '@/routes/products/variants';
import type { Team } from '@/types';

type Props = {
    product: {
        id: number;
        name: string;
        description: string | null;
        is_active: boolean;
    };
    variants: {
        id: number;
        name: string;
        sku: string;
        price: string;
        is_active: boolean;
    }[];
    canManage: boolean;
    category: { name: string; is_active: boolean } | null;
    images: { id: number; url: string }[];
};

export default function ProductShow({
    product,
    variants,
    canManage,
    category,
    images,
}: Props) {
    const { currentTeam } = usePage<{ currentTeam: Team | null }>().props;
    if (!currentTeam) return null;
    const args = { current_team: currentTeam.slug, product: product.id };
    return (
        <>
            <Head title={product.name} />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="flex flex-col gap-2">
                        <h1 className="text-2xl font-semibold wrap-break-word">
                            {product.name}
                        </h1>
                        <Badge
                            variant={
                                product.is_active ? 'default' : 'secondary'
                            }
                            className="self-start"
                        >
                            {product.is_active ? 'Activo' : 'Inactivo'}
                        </Badge>
                    </div>
                    {canManage && (
                        <Button variant="outline" asChild>
                            <Link href={edit(args)}>Editar producto</Link>
                        </Button>
                    )}
                </div>
                <p className="max-w-3xl wrap-break-word whitespace-pre-wrap text-muted-foreground">
                    {product.description || 'Sin descripción.'}
                </p>
                <p className="text-sm text-muted-foreground">
                    Categoría: {category?.name ?? 'Sin categoría'}
                    {category && !category.is_active ? ' (inactiva)' : ''}
                </p>
                <ProductImages
                    product={{ id: product.id, name: product.name }}
                    images={images}
                    canManage={canManage}
                />
                <section className="flex flex-col gap-4">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <h2 className="text-xl font-semibold">Variantes</h2>
                        {canManage && (
                            <Button asChild>
                                <Link href={createVariant(args)}>
                                    Agregar variante
                                </Link>
                            </Button>
                        )}
                    </div>
                    <p className="text-sm text-muted-foreground">
                        Cada variante tiene su propio SKU y precio en Bs.
                    </p>
                    {variants.length === 0 ? (
                        <p className="rounded-xl border border-dashed p-8 text-center text-muted-foreground">
                            Este producto todavía no tiene variantes.
                            {canManage &&
                                ' Agrega una variante “Estándar” si no tiene tallas o colores.'}
                        </p>
                    ) : (
                        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            {variants.map((variant) => (
                                <article
                                    key={variant.id}
                                    className="flex flex-col gap-3 rounded-xl border p-5"
                                >
                                    <div className="flex items-start justify-between gap-3">
                                        <h3 className="font-medium wrap-break-word">
                                            {variant.name}
                                        </h3>
                                        <Badge
                                            variant={
                                                variant.is_active
                                                    ? 'default'
                                                    : 'secondary'
                                            }
                                        >
                                            {variant.is_active
                                                ? 'Activa'
                                                : 'Inactiva'}
                                        </Badge>
                                    </div>
                                    <p className="text-sm wrap-break-word text-muted-foreground">
                                        SKU: {variant.sku}
                                    </p>
                                    <p className="text-lg font-semibold">
                                        {Number(variant.price).toLocaleString(
                                            'es-BO',
                                            {
                                                minimumFractionDigits: 2,
                                                maximumFractionDigits: 2,
                                            },
                                        )}{' '}
                                        Bs
                                    </p>
                                    {canManage && (
                                        <Button
                                            variant="outline"
                                            className="self-start"
                                            asChild
                                        >
                                            <Link
                                                href={editVariant({
                                                    ...args,
                                                    variant: variant.id,
                                                })}
                                            >
                                                Editar variante
                                            </Link>
                                        </Button>
                                    )}
                                </article>
                            ))}
                        </div>
                    )}
                </section>
                <Button variant="outline" className="self-start" asChild>
                    <Link href={index(currentTeam.slug)}>
                        Volver al catálogo
                    </Link>
                </Button>
            </div>
        </>
    );
}
ProductShow.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: props.currentTeam
        ? [{ title: 'Productos', href: index(props.currentTeam.slug) }]
        : [],
});
