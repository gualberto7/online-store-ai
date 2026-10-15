import { Form, Head, Link, usePage } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Team } from '@/types';
import { index, store, update } from '@/routes/categories';

type Category = {
    id: number;
    name: string;
    description: string | null;
    is_active: boolean;
};

export default function CategoryForm({ category }: { category?: Category }) {
    const { currentTeam } = usePage<{ currentTeam: Team | null }>().props;
    if (!currentTeam) return null;
    const title = category ? 'Editar categoría' : 'Nueva categoría';

    return (
        <>
            <Head title={title} />
            <div className="flex max-w-2xl flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold">{title}</h1>
                    <p className="text-muted-foreground">
                        Organiza los productos de tu catálogo.
                    </p>
                </div>
                <Form
                    key={category?.id ?? 'create'}
                    {...(category
                        ? update.form({
                              current_team: currentTeam.slug,
                              category: category.id,
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
                                    defaultValue={category?.name}
                                    placeholder="Calzado"
                                    maxLength={255}
                                    required
                                    aria-invalid={!!errors.name}
                                />
                                <InputError message={errors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="description">Descripción</Label>
                                <Input
                                    id="description"
                                    name="description"
                                    defaultValue={category?.description ?? ''}
                                    placeholder="Describe los productos de esta categoría"
                                    maxLength={2000}
                                    aria-invalid={!!errors.description}
                                />
                                <InputError message={errors.description} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="is_active">Estado</Label>
                                <select
                                    id="is_active"
                                    name="is_active"
                                    defaultValue={
                                        category?.is_active === false
                                            ? '0'
                                            : '1'
                                    }
                                    className="h-9 rounded-md border border-input bg-background px-3 text-sm focus-visible:ring-2 focus-visible:ring-ring"
                                    aria-invalid={!!errors.is_active}
                                >
                                    <option value="1">Activa</option>
                                    <option value="0">Inactiva</option>
                                </select>
                                <p className="text-sm text-muted-foreground">
                                    Puedes desactivar una categoría sin borrar
                                    su información.
                                </p>
                                <InputError message={errors.is_active} />
                            </div>
                            <div className="flex gap-3">
                                <Button type="submit" disabled={processing}>
                                    {processing
                                        ? 'Guardando…'
                                        : 'Guardar categoría'}
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

CategoryForm.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: props.currentTeam
        ? [{ title: 'Categorías', href: index(props.currentTeam.slug) }]
        : [],
});
