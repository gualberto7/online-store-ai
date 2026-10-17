import { Form, usePage } from '@inertiajs/react';
import { useState } from 'react';
import ProductImageUploader from '@/components/product-image-uploader';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { destroy } from '@/routes/products/images';

type Props = {
    product: { id: number; name: string };
    images: { id: number; url: string }[];
    canManage: boolean;
};

export default function ProductImages({ product, images, canManage }: Props) {
    const { currentTeam } = usePage().props;
    const [uploadedImages, setUploadedImages] = useState<
        { id: number; url: string }[]
    >([]);
    const [uploading, setUploading] = useState(false);
    const gallery = [
        ...images,
        ...uploadedImages.filter(
            (image) => !images.some((existing) => existing.id === image.id),
        ),
    ];
    if (!currentTeam) return null;
    const args = { current_team: currentTeam.slug, product: product.id };
    return (
        <section className="flex flex-col gap-4">
            <h2 className="text-xl font-semibold">Imágenes</h2>
            <p className="text-sm text-muted-foreground">
                La primera imagen se usa como portada. Hasta 10 imágenes por
                producto.
            </p>
            {gallery.length === 0 ? (
                <p className="rounded-xl border border-dashed p-8 text-center text-muted-foreground">
                    Este producto todavía no tiene imágenes.
                </p>
            ) : (
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {gallery.map((image, position) => (
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
                                        onSuccess={() =>
                                            setUploadedImages((current) =>
                                                current.filter(
                                                    (uploaded) =>
                                                        uploaded.id !==
                                                        image.id,
                                                ),
                                            )
                                        }
                                    >
                                        {({ processing }) => (
                                            <Button
                                                type="submit"
                                                variant="outline"
                                                size="sm"
                                                disabled={
                                                    processing || uploading
                                                }
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
            {canManage && (
                <ProductImageUploader
                    productId={product.id}
                    teamSlug={currentTeam.slug}
                    remainingSlots={Math.max(0, 10 - gallery.length)}
                    onUploaded={(image) =>
                        setUploadedImages((current) => [...current, image])
                    }
                    onUploadingChange={setUploading}
                />
            )}
        </section>
    );
}
