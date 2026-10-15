import { Head, Link, usePage } from '@inertiajs/react';
import { Package, Plus } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { create, index, show } from '@/routes/products';
import type { Team } from '@/types';

type Product = {
    id: number;
    name: string;
    is_active: boolean;
    variants_count: number;
    category: { name: string } | null;
    images: { id: number; url: string }[];
};
type Props = {
    products: { data: Product[]; current_page: number; last_page: number };
    canManage: boolean;
};

export default function ProductsIndex({ products, canManage }: Props) {
    const { currentTeam } = usePage<{ currentTeam: Team | null }>().props;
    if (!currentTeam) return null;
    return (
        <>
            <Head title="Productos" />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">Productos</h1>
                        <p className="text-muted-foreground">
                            Catálogo compartido entre las sucursales de{' '}
                            {currentTeam.name}.
                        </p>
                    </div>
                    {canManage && (
                        <Button asChild>
                            <Link href={create(currentTeam.slug)}>
                                <Plus />
                                Nuevo producto
                            </Link>
                        </Button>
                    )}
                </div>
                {products.data.length === 0 ? (
                    <div className="flex flex-col items-center gap-3 rounded-xl border border-dashed p-12 text-center">
                        <Package className="size-10 text-muted-foreground" />
                        <h2 className="text-lg font-medium">
                            Todavía no hay productos
                        </h2>
                        <p className="text-muted-foreground">
                            {canManage
                                ? 'Crea un producto y agrega sus variantes con SKU y precio.'
                                : 'Un administrador puede agregar productos al catálogo.'}
                        </p>
                    </div>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {products.data.map((product) => (
                            <article
                                key={product.id}
                                className="flex flex-col gap-4 rounded-xl border p-5"
                            >
                                {product.images[0] && (
                                    <img
                                        src={product.images[0].url}
                                        alt={product.name}
                                        loading="lazy"
                                        className="aspect-square w-full rounded-lg bg-muted object-contain"
                                    />
                                )}
                                <p className="text-sm text-muted-foreground">
                                    {product.category?.name ?? 'Sin categoría'}
                                </p>
                                <div className="flex items-start justify-between gap-3">
                                    <h2 className="font-semibold wrap-break-word">
                                        {product.name}
                                    </h2>
                                    <Badge
                                        variant={
                                            product.is_active
                                                ? 'default'
                                                : 'secondary'
                                        }
                                    >
                                        {product.is_active
                                            ? 'Activo'
                                            : 'Inactivo'}
                                    </Badge>
                                </div>
                                <p className="text-sm text-muted-foreground">
                                    {product.variants_count} variantes
                                </p>
                                <Button
                                    variant="outline"
                                    className="self-start"
                                    asChild
                                >
                                    <Link
                                        href={show({
                                            current_team: currentTeam.slug,
                                            product: product.id,
                                        })}
                                    >
                                        Ver producto
                                    </Link>
                                </Button>
                            </article>
                        ))}
                    </div>
                )}
                {products.last_page > 1 && (
                    <nav
                        aria-label="Páginas del catálogo"
                        className="flex items-center gap-3"
                    >
                        {products.current_page > 1 && (
                            <Button variant="outline" asChild>
                                <Link
                                    href={index(currentTeam.slug, {
                                        query: {
                                            page: products.current_page - 1,
                                        },
                                    })}
                                >
                                    Anterior
                                </Link>
                            </Button>
                        )}
                        <span className="text-sm">
                            Página {products.current_page} de{' '}
                            {products.last_page}
                        </span>
                        {products.current_page < products.last_page && (
                            <Button variant="outline" asChild>
                                <Link
                                    href={index(currentTeam.slug, {
                                        query: {
                                            page: products.current_page + 1,
                                        },
                                    })}
                                >
                                    Siguiente
                                </Link>
                            </Button>
                        )}
                    </nav>
                )}
            </div>
        </>
    );
}
ProductsIndex.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: props.currentTeam
        ? [{ title: 'Productos', href: index(props.currentTeam.slug) }]
        : [],
});
