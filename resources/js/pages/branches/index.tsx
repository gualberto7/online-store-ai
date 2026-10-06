import { Head, Link, usePage } from '@inertiajs/react';
import { MapPin, Plus, Store } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { Team } from '@/types';
import { create, edit, index } from '@/routes/branches';
import { index as inventoryIndex } from '@/routes/branches/inventory';

type Branch = { id: number; name: string; address: string; is_active: boolean };

export default function BranchesIndex({
    branches,
    canManage,
}: {
    branches: Branch[];
    canManage: boolean;
}) {
    const { currentTeam } = usePage<{ currentTeam: Team | null }>().props;
    if (!currentTeam) return null;

    return (
        <>
            <Head title="Sucursales" />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">Sucursales</h1>
                        <p className="text-muted-foreground">
                            Administra las ubicaciones de {currentTeam.name}.
                        </p>
                    </div>
                    {canManage && (
                        <Button asChild>
                            <Link href={create(currentTeam.slug)}>
                                <Plus />
                                Nueva sucursal
                            </Link>
                        </Button>
                    )}
                </div>
                {branches.length === 0 ? (
                    <div className="flex flex-col items-center gap-3 rounded-xl border border-dashed p-12 text-center">
                        <Store className="size-10 text-muted-foreground" />
                        <h2 className="text-lg font-medium">
                            Todavía no hay sucursales
                        </h2>
                        <p className="text-muted-foreground">
                            {canManage
                                ? 'Crea la primera ubicación de tu negocio.'
                                : 'Un administrador puede crear la primera sucursal.'}
                        </p>
                        {canManage && (
                            <Button asChild>
                                <Link href={create(currentTeam.slug)}>
                                    Crear sucursal
                                </Link>
                            </Button>
                        )}
                    </div>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {branches.map((branch) => (
                            <article
                                key={branch.id}
                                className="flex flex-col gap-4 rounded-xl border p-5"
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <h2 className="font-semibold wrap-break-word">
                                        {branch.name}
                                    </h2>
                                    <Badge
                                        variant={
                                            branch.is_active
                                                ? 'default'
                                                : 'secondary'
                                        }
                                    >
                                        {branch.is_active
                                            ? 'Activa'
                                            : 'Inactiva'}
                                    </Badge>
                                </div>
                                <p className="flex items-start gap-2 text-sm text-muted-foreground">
                                    <MapPin className="size-4 shrink-0" />
                                    <span className="wrap-break-word">
                                        {branch.address}
                                    </span>
                                </p>
                                <Button
                                    variant="outline"
                                    className="self-start"
                                    asChild
                                >
                                    <Link
                                        href={inventoryIndex({
                                            current_team: currentTeam.slug,
                                            branch: branch.id,
                                        })}
                                    >
                                        Ver inventario
                                    </Link>
                                </Button>
                                {canManage && (
                                    <Button
                                        variant="outline"
                                        className="mt-auto self-start"
                                        asChild
                                    >
                                        <Link
                                            href={edit({
                                                current_team: currentTeam.slug,
                                                branch: branch.id,
                                            })}
                                        >
                                            Editar sucursal
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

BranchesIndex.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: props.currentTeam
        ? [{ title: 'Sucursales', href: index(props.currentTeam.slug) }]
        : [],
});
