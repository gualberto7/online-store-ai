import { Form, usePage } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { destroy, store } from '@/routes/products/images';

type Props = {
    product: { id: number; name: string };
    images: { id: number; url: string }[];
    canManage: boolean;
};

export default function ProductImages({ product, images, canManage }: Props) {
    const { currentTeam } = usePage().props;
    if (!currentTeam) return null;
    const args = { current_team: currentTeam.slug, product: product.id };
    return (
        <section className="flex flex-col gap-4">
            <h2 className="text-xl font-semibold">Imágenes</h2>
            <p className="text-sm text-muted-foreground">
                La primera imagen se usa como portada. Hasta 10 imágenes por
                producto.
            </p>
            {images.length === 0 ? (
                <p className="rounded-xl border border-dashed p-8 text-center text-muted-foreground">
                    Este producto todavía no tiene imágenes.
                </p>
            ) : (
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {images.map((image, position) => (
                        <figure
                            key={image.id}
                            className="flex flex-col gap-3 rounded-xl border p-3"
                        >
                            <img
                                src={image.url}
                                alt={`${product.name} · imagen ${position + 1}`}
                                loading="lazy"
                                className="aspect-square w-full rounded-lg bg-muted object-contain"
                            />
                            <figcaption className="flex flex-wrap items-center justify-between gap-2">
                                {position === 0 && (
                                    <Badge variant="secondary">Portada</Badge>
                                )}
                                {canManage && (
                                    <Form
                                        {...destroy.form({
                                            ...args,
                                            image: image.id,
                                        })}
                                        options={{ preserveScroll: true }}
                                    >
                                        {({ processing }) => (
                                            <Button
                                                type="submit"
                                                variant="outline"
                                                size="sm"
                                                disabled={processing}
                                                aria-label={`Eliminar imagen ${position + 1}`}
                                            >
                                                {processing
                                                    ? 'Eliminando…'
                                                    : 'Eliminar'}
                                            </Button>
                                        )}
                                    </Form>
                                )}
                            </figcaption>
                        </figure>
                    ))}
                </div>
            )}
            {canManage && images.length < 10 && (
                <Form
                    {...store.form(args)}
                    resetOnSuccess
                    options={{ preserveScroll: true }}
                    className="flex max-w-xl flex-col gap-3 rounded-xl border p-4"
                >
                    {({ errors, processing, progress }) => (
                        <>
                            <Label htmlFor="image">Agregar imagen</Label>
                            <Input
                                id="image"
                                name="image"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                required
                                aria-invalid={!!errors.image}
                            />
                            <p className="text-sm text-muted-foreground">
                                JPG, PNG o WebP. Máximo 1 MB y 4096 × 4096
                                píxeles. Sube una imagen cada vez.
                            </p>
                            <InputError message={errors.image} />
                            {progress && (
                                <progress
                                    value={progress.percentage}
                                    max="100"
                                    aria-label="Progreso de carga"
                                    className="w-full"
                                />
                            )}
                            <Button
                                type="submit"
                                className="self-start"
                                disabled={processing}
                            >
                                {processing ? 'Subiendo…' : 'Subir imagen'}
                            </Button>
                        </>
                    )}
                </Form>
            )}
        </section>
    );
}
