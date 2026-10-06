import { Form, Head, Link, usePage } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Team } from '@/types';
import { index, store, update } from '@/routes/branches';

type Branch = { id: number; name: string; address: string; is_active: boolean };

export default function BranchForm({ branch }: { branch?: Branch }) {
    const { currentTeam } = usePage<{ currentTeam: Team | null }>().props;
    if (!currentTeam) return null;
    const title = branch ? 'Editar sucursal' : 'Nueva sucursal';

    return (
        <>
            <Head title={title} />
            <div className="flex max-w-2xl flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold">{title}</h1>
                    <p className="text-muted-foreground">
                        Registra la ubicación y el estado de la sucursal.
                    </p>
                </div>
                <Form
                    key={branch?.id ?? 'create'}
                    {...(branch
                        ? update.form({
                              current_team: currentTeam.slug,
                              branch: branch.id,
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
                                    defaultValue={branch?.name}
                                    placeholder="Sucursal Centro"
                                    maxLength={255}
                                    required
                                    aria-invalid={!!errors.name}
                                />
                                <InputError message={errors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="address">Dirección</Label>
                                <Input
                                    id="address"
                                    name="address"
                                    defaultValue={branch?.address}
                                    placeholder="Calle, número y ciudad"
                                    maxLength={500}
                                    required
                                    aria-invalid={!!errors.address}
                                />
                                <InputError message={errors.address} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="is_active">Estado</Label>
                                <select
                                    id="is_active"
                                    name="is_active"
                                    defaultValue={
                                        branch?.is_active === false ? '0' : '1'
                                    }
                                    className="h-9 rounded-md border border-input bg-background px-3 text-sm focus-visible:ring-2 focus-visible:ring-ring"
                                    aria-invalid={!!errors.is_active}
                                >
                                    <option value="1">Activa</option>
                                    <option value="0">Inactiva</option>
                                </select>
                                <p className="text-sm text-muted-foreground">
                                    Puedes desactivar una sucursal sin borrar su
                                    información.
                                </p>
                                <InputError message={errors.is_active} />
                            </div>
                            <div className="flex gap-3">
                                <Button type="submit" disabled={processing}>
                                    {processing
                                        ? 'Guardando…'
                                        : 'Guardar sucursal'}
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

BranchForm.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: props.currentTeam
        ? [{ title: 'Sucursales', href: index(props.currentTeam.slug) }]
        : [],
});
