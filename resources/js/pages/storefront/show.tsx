import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft, Check, MapPin, Package } from 'lucide-react';
import { useState } from 'react';
import { home } from '@/routes';
import { update } from '@/routes/storefront/cart';
import { buttonClass, fieldClass } from './cart';

type Props = {
    storefront: { name: string };
    product: {
        id: number;
        name: string;
        description: string | null;
        category: string | null;
        images: { id: number; url: string }[];
        variants: {
            id: number;
            name: string;
            sku: string;
            price: string;
            available_at: number[];
        }[];
    };
    branches: { id: number; name: string; address: string }[];
};

export default function StorefrontShow({
    storefront,
    product,
    branches,
}: Props) {
    const [variantId, setVariantId] = useState(product.variants[0]?.id);
    const [imageId, setImageId] = useState(product.images[0]?.id);
    const selectedVariant =
        product.variants.find((variant) => variant.id === variantId) ??
        product.variants[0];
    const selectedImage =
        product.images.find((image) => image.id === imageId) ??
        product.images[0];
    return (
        <>
            <Head title={`${product.name} · ${storefront.name}`} />
            <Link
                href={home()}
                className="mb-8 inline-flex items-center gap-2 text-sm text-stone-500 hover:text-teal-900"
            >
                <ArrowLeft className="size-4" />
                Volver al catálogo
            </Link>
            <div className="grid items-start gap-8 lg:grid-cols-2 lg:gap-14">
                <section
                    aria-label="Galería del producto"
                    className="flex min-w-0 flex-col gap-4"
                >
                    <div className="flex aspect-square items-center justify-center overflow-hidden rounded-3xl bg-stone-100 p-7">
                        {selectedImage ? (
                            <img
                                src={selectedImage.url}
                                alt={product.name}
                                className="size-full object-contain"
                            />
                        ) : (
                            <div className="flex flex-col items-center gap-4 text-stone-400">
                                <Package className="size-20" strokeWidth={1} />
                                <span className="text-sm">
                                    Imagen no disponible
                                </span>
                            </div>
                        )}
                    </div>
                    {product.images.length > 1 && (
                        <div className="flex flex-wrap gap-3">
                            {product.images.map((image, position) => (
                                <button
                                    key={image.id}
                                    type="button"
                                    onClick={() => setImageId(image.id)}
                                    aria-label={`Ver imagen ${position + 1} de ${product.name}`}
                                    aria-pressed={
                                        selectedImage?.id === image.id
                                    }
                                    className={`size-20 overflow-hidden rounded-xl border-2 bg-stone-100 p-2 ${selectedImage?.id === image.id ? 'border-teal-800' : 'border-transparent hover:border-stone-300'}`}
                                >
                                    <img
                                        src={image.url}
                                        alt=""
                                        className="size-full object-contain"
                                    />
                                </button>
                            ))}
                        </div>
                    )}
                </section>
                <section className="flex min-w-0 flex-col gap-6">
                    {selectedVariant && (
                        <Form
                            {...update.form()}
                            className="order-last grid gap-3"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <input
                                        type="hidden"
                                        name="variant_id"
                                        value={selectedVariant.id}
                                    />
                                    <label htmlFor="cart-quantity">
                                        Cantidad
                                    </label>
                                    <input
                                        id="cart-quantity"
                                        name="quantity"
                                        type="number"
                                        min="1"
                                        max="1000"
                                        defaultValue="1"
                                        className={fieldClass}
                                    />
                                    {errors.quantity && (
                                        <p
                                            role="alert"
                                            className="text-red-700"
                                        >
                                            {errors.quantity}
                                        </p>
                                    )}
                                    <button
                                        className={buttonClass}
                                        disabled={
                                            processing ||
                                            !selectedVariant.available_at.length
                                        }
                                    >
                                        {processing
                                            ? 'Agregando…'
                                            : 'Agregar al carrito'}
                                    </button>
                                </>
                            )}
                        </Form>
                    )}
                    <div>
                        <p className="mb-3 text-xs tracking-widest text-teal-800 uppercase">
                            {product.category ?? 'Nuestra colección'}
                        </p>
                        <h1 className="text-3xl leading-tight font-medium tracking-tight wrap-break-word sm:text-4xl">
                            {product.name}
                        </h1>
                    </div>
                    {selectedVariant && (
                        <div className="flex flex-wrap items-center gap-4">
                            <p className="text-3xl font-medium">
                                {Number(selectedVariant.price).toLocaleString(
                                    'es-BO',
                                    {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2,
                                    },
                                )}
                                <span className="ml-2 text-lg text-stone-500">
                                    Bs
                                </span>
                            </p>
                            <span
                                className={`rounded-full px-3 py-1 text-xs font-medium ${selectedVariant.available_at.length > 0 ? 'bg-teal-900/10 text-teal-900' : 'bg-stone-200 text-stone-600'}`}
                            >
                                {selectedVariant.available_at.length > 0
                                    ? 'Disponible en sucursal'
                                    : 'Sin existencias'}
                            </span>
                        </div>
                    )}
                    <p className="leading-relaxed wrap-break-word whitespace-pre-wrap text-stone-500">
                        {product.description ||
                            'Consulta sus variantes y disponibilidad en nuestras sucursales.'}
                    </p>
                    <fieldset className="border-t border-stone-200 pt-5">
                        <legend className="pr-4 text-sm font-medium">
                            Elige tu variante
                        </legend>
                        <div className="flex flex-wrap gap-3 pt-3">
                            {product.variants.map((variant) => (
                                <button
                                    key={variant.id}
                                    type="button"
                                    onClick={() => setVariantId(variant.id)}
                                    aria-pressed={
                                        selectedVariant?.id === variant.id
                                    }
                                    className={`inline-flex items-center gap-2 rounded-lg border px-4 py-3 text-sm transition-colors ${selectedVariant?.id === variant.id ? 'border-teal-900 bg-teal-900 text-white' : 'border-stone-200 bg-white hover:border-teal-800'}`}
                                >
                                    {selectedVariant?.id === variant.id && (
                                        <Check className="size-3.5" />
                                    )}
                                    {variant.name}
                                </button>
                            ))}
                        </div>
                        {selectedVariant && (
                            <p className="mt-3 text-xs text-stone-400">
                                Código: {selectedVariant.sku}
                            </p>
                        )}
                    </fieldset>
                    <div className="rounded-2xl border border-stone-200 bg-white p-5">
                        <h2 className="mb-4 flex items-center gap-2 font-medium">
                            <MapPin className="size-4 text-teal-800" />
                            Disponibilidad por sucursal
                        </h2>
                        {branches.length === 0 ? (
                            <p className="text-sm text-stone-500">
                                No hay sucursales disponibles para consultar.
                            </p>
                        ) : (
                            <ul className="divide-y divide-stone-100">
                                {branches.map((branch) => (
                                    <li
                                        key={branch.id}
                                        className="flex items-start justify-between gap-4 py-3 first:pt-0 last:pb-0"
                                    >
                                        <div>
                                            <p className="text-sm font-medium">
                                                {branch.name}
                                            </p>
                                            <p className="mt-1 text-xs leading-relaxed text-stone-500">
                                                {branch.address}
                                            </p>
                                        </div>
                                        <span
                                            className={`shrink-0 text-xs font-medium ${selectedVariant?.available_at.includes(branch.id) ? 'text-teal-800' : 'text-stone-400'}`}
                                        >
                                            {selectedVariant?.available_at.includes(
                                                branch.id,
                                            )
                                                ? 'Disponible'
                                                : 'Sin existencias'}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                </section>
            </div>
        </>
    );
}
