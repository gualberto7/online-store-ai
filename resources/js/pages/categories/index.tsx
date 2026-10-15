import { Head, Link, usePage } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { create, edit, index } from '@/routes/categories';

type Props = {
    categories: {
        id: number;
        name: string;
        description: string | null;
        is_active: boolean;
        products_count: number;
    }[];
    canManage: boolean;
};

export default function CategoriesIndex({ categories, canManage }: Props) {
    const { currentTeam } = usePage().props;
    if (!currentTeam) return null;
    return (
        <>
            <Head title="Categorías" />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">Categorías</h1>
                        <p className="text-muted-foreground">
                            Organiza el catálogo de {currentTeam.name}.
                        </p>
                    </div>
                    {canManage && (
                        <Button asChild>
                            <Link href={create(currentTeam.slug)}>
                                Nueva categoría
                            </Link>
                        </Button>
                    )}
                </div>
                {categories.length === 0 ? (
                    <p className="rounded-xl border border-dashed p-10 text-center text-muted-foreground">
                        Todavía no hay categorías.
                        {canManage &&
                            ' Crea la primera para organizar tus productos.'}
                    </p>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {categories.map((category) => (
                            <article
                                key={category.id}
                                className="flex flex-col gap-4 rounded-xl border p-5"
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <h2 className="font-semibold wrap-break-word">
                                        {category.name}
                                    </h2>
                                    <Badge
                                        variant={
                                            category.is_active
                                                ? 'default'
                                                : 'secondary'
                                        }
                                    >
                                        {category.is_active
                                            ? 'Activa'
                                            : 'Inactiva'}
                                    </Badge>
                                </div>
                                <p className="text-sm wrap-break-word whitespace-pre-wrap text-muted-foreground">
                                    {category.description || 'Sin descripción.'}
                                </p>
                                <p className="text-sm">
                                    {category.products_count} productos
                                </p>
                                {canManage && (
                                    <Button
                                        variant="outline"
                                        className="mt-auto self-start"
                                        asChild
                                    >
                                        <Link
                                            href={edit({
                                                current_team: currentTeam.slug,
                                                category: category.id,
                                            })}
                                        >
                                            Editar categoría
                                        </Link>
                                    </Button>
                                )}
                            </article>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}
CategoriesIndex.layout = (props: {
    currentTeam?: { slug: string } | null;
}) => ({
    breadcrumbs: props.currentTeam
        ? [{ title: 'Categorías', href: index(props.currentTeam.slug) }]
        : [],
});
