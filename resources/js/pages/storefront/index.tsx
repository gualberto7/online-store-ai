import { Form, Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    MapPin,
    Package,
    Search,
    SlidersHorizontal,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { home } from '@/routes';
import { show } from '@/routes/storefront';

type Product = {
    id: number;
    name: string;
    category: string | null;
    image: string | null;
    price_from: string;
    available: boolean;
};
type Filters = {
    q: string;
    category: number | '';
    branch: number | '';
    available: boolean;
    sort: string;
};
type Props = {
    storefront: { name: string };
    products: {
        data: Product[];
        total: number;
        current_page: number;
        last_page: number;
    };
    categories: { id: number; name: string }[];
    branches: { id: number; name: string; address: string }[];
    filters: Filters;
};
const money = (value: string) =>
    `${Number(value).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} Bs`;
const selectClass =
    'h-11 min-w-0 rounded-lg border border-stone-200 bg-white px-3 text-sm text-stone-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal-700';

export default function StorefrontIndex({
    storefront,
    products,
    categories,
    branches,
    filters,
}: Props) {
    const featured = products.data[0];
    const hasFilters =
        filters.q ||
        filters.category ||
        filters.branch ||
        filters.available ||
        filters.sort !== 'name';
    function pageLink(page: number) {
        return home({
            query: {
                ...filters,
                available: filters.available ? 1 : undefined,
                page,
            },
        });
    }
    return (
        <>
            <Head title={`Catálogo · ${storefront.name}`} />
            <section className="grid items-center gap-8 pb-12 lg:grid-cols-[1.2fr_0.8fr] lg:gap-16 lg:pb-16">
                <div className="flex flex-col items-start gap-5">
                    <span className="rounded-full border border-teal-900/20 px-3 py-1 text-xs font-medium tracking-widest text-teal-900 uppercase">
                        Tu próxima elección
                    </span>
                    <h1 className="max-w-2xl text-4xl leading-tight font-medium tracking-tight text-stone-900 sm:text-5xl lg:text-6xl">
                        Lo que buscas,
                        <br />
                        <span className="text-teal-800">
                            para tu día a día.
                        </span>
                    </h1>
                    <p className="max-w-lg text-base leading-relaxed text-stone-500 sm:text-lg">
                        Explora nuestro catálogo, encuentra tu variante y
                        consulta en qué sucursal está disponible.
                    </p>
                    <div className="flex items-center gap-2 pt-2 text-sm text-teal-900">
                        <MapPin className="size-4" />
                        {branches.length > 0
                            ? `${branches.length} ${branches.length === 1 ? 'sucursal para encontrarnos' : 'sucursales para encontrarnos'}`
                            : 'Descubre nuestra selección'}
                    </div>
                </div>
                <div className="relative flex min-h-64 items-center justify-center overflow-hidden rounded-3xl bg-stone-200/50 p-6 sm:min-h-80">
                    {featured?.image ? (
                        <Link
                            href={show(featured.id)}
                            className="flex h-full w-full flex-col gap-5"
                        >
                            <img
                                src={featured.image}
                                alt={featured.name}
                                className="h-56 w-full object-contain mix-blend-multiply"
                            />
                            <div className="flex items-end justify-between gap-4">
                                <div>
                                    <p className="mb-1 text-xs tracking-widest text-stone-500 uppercase">
                                        Del catálogo
                                    </p>
                                    <p className="text-lg font-medium">
                                        {featured.name}
                                    </p>
                                </div>
                                <ArrowRight className="size-5 shrink-0" />
                            </div>
                        </Link>
                    ) : (
                        <div className="flex flex-col items-center gap-5 py-5 text-teal-900">
                            <ShoppingIllustration />
                            <span className="text-sm tracking-wide">
                                Una colección para descubrir
                            </span>
                        </div>
                    )}
                </div>
            </section>
            <section
                className="flex flex-col gap-6"
                aria-labelledby="catalog-title"
            >
                <div className="flex flex-wrap items-end justify-between gap-4 border-t border-stone-200 pt-8">
                    <div>
                        <p className="mb-2 text-xs tracking-widest text-stone-500 uppercase">
                            Explora la colección
                        </p>
                        <h2
                            id="catalog-title"
                            className="text-2xl font-medium tracking-tight sm:text-3xl"
                        >
                            Nuestro catálogo
                        </h2>
                    </div>
                    <p className="text-sm text-stone-500">
                        {products.total}{' '}
                        {products.total === 1 ? 'producto' : 'productos'}
                    </p>
                </div>
                <Form
                    key={JSON.stringify(filters)}
                    {...home.form()}
                    className="flex flex-col gap-4 rounded-2xl border border-stone-200 bg-white p-4 sm:p-5"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-[1.5fr_1fr_1fr_1fr]">
                                <div className="relative">
                                    <label htmlFor="q" className="sr-only">
                                        Buscar productos
                                    </label>
                                    <Search className="absolute top-3.5 left-3 size-4 text-stone-400" />
                                    <Input
                                        id="q"
                                        name="q"
                                        defaultValue={filters.q}
                                        placeholder="Buscar por nombre o código…"
                                        maxLength={120}
                                        className="h-11 border-stone-200 bg-white pl-9 text-stone-900 placeholder:text-stone-400"
                                    />
                                </div>
                                <select
                                    name="category"
                                    aria-label="Categoría"
                                    defaultValue={filters.category}
                                    className={selectClass}
                                >
                                    <option value="">
                                        Todas las categorías
                                    </option>
                                    {categories.map((category) => (
                                        <option
                                            key={category.id}
                                            value={category.id}
                                        >
                                            {category.name}
                                        </option>
                                    ))}
                                </select>
                                <select
                                    name="branch"
                                    aria-label="Sucursal"
                                    defaultValue={filters.branch}
                                    className={selectClass}
                                >
                                    <option value="">
                                        Todas las sucursales
                                    </option>
                                    {branches.map((branch) => (
                                        <option
                                            key={branch.id}
                                            value={branch.id}
                                        >
                                            {branch.name}
                                        </option>
                                    ))}
                                </select>
                                <select
                                    name="sort"
                                    aria-label="Ordenar productos"
                                    defaultValue={filters.sort}
                                    className={selectClass}
                                >
                                    <option value="name">Nombre: A–Z</option>
                                    <option value="price_asc">
                                        Menor precio primero
                                    </option>
                                    <option value="price_desc">
                                        Mayor precio primero
                                    </option>
                                </select>
                            </div>
                            <div className="flex flex-wrap items-center justify-between gap-4">
                                <label className="flex cursor-pointer items-center gap-2 text-sm text-stone-600">
                                    <input
                                        type="checkbox"
                                        name="available"
                                        value="1"
                                        defaultChecked={filters.available}
                                        className="size-4 accent-teal-800"
                                    />
                                    Solo con disponibilidad
                                </label>
                                <div className="flex items-center gap-4">
                                    {hasFilters && (
                                        <Link
                                            href={home()}
                                            className="text-sm text-stone-500 underline underline-offset-4"
                                        >
                                            Limpiar filtros
                                        </Link>
                                    )}
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        className="bg-teal-900 text-white hover:bg-teal-950"
                                    >
                                        <SlidersHorizontal className="size-4" />
                                        {processing
                                            ? 'Buscando…'
                                            : 'Aplicar filtros'}
                                    </Button>
                                </div>
                            </div>
                            {Object.values(errors).length > 0 && (
                                <p
                                    role="alert"
                                    className="text-sm text-red-700"
                                >
                                    {Object.values(errors)[0]}
                                </p>
                            )}
                        </>
                    )}
                </Form>
                {products.data.length === 0 ? (
                    <div className="flex flex-col items-center gap-4 rounded-2xl border border-dashed border-stone-300 px-6 py-16 text-center">
                        <Package className="size-10 text-stone-400" />
                        <h3 className="text-xl font-medium">
                            {hasFilters
                                ? 'No encontramos productos con esos filtros'
                                : 'Estamos preparando nuestro catálogo'}
                        </h3>
                        <p className="max-w-md text-sm leading-relaxed text-stone-500">
                            {hasFilters
                                ? 'Prueba con otra palabra, categoría o sucursal.'
                                : 'Aquí podrás explorar los productos, sus variantes y su disponibilidad.'}
                        </p>
                        {hasFilters && (
                            <Link
                                href={home()}
                                className="text-sm font-medium text-teal-800 underline underline-offset-4"
                            >
                                Ver todos los productos
                            </Link>
                        )}
                    </div>
                ) : (
                    <div className="grid gap-x-5 gap-y-8 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        {products.data.map((product) => (
                            <Link
                                key={product.id}
                                href={show(product.id)}
                                className="group flex min-w-0 flex-col gap-3 rounded-2xl focus-visible:ring-2 focus-visible:ring-teal-700 focus-visible:outline-none"
                            >
                                <div className="relative flex aspect-[4/5] items-center justify-center overflow-hidden rounded-2xl border border-stone-200/50 bg-stone-100 p-5">
                                    {product.image ? (
                                        <img
                                            src={product.image}
                                            alt={product.name}
                                            loading="lazy"
                                            className="size-full object-contain transition-transform duration-300 group-hover:scale-105"
                                        />
                                    ) : (
                                        <Package className="size-16 text-stone-300" />
                                    )}
                                    <span
                                        className={`absolute top-3 left-3 rounded-full px-2.5 py-1 text-[11px] font-medium ${product.available ? 'bg-teal-950 text-white' : 'bg-white/90 text-stone-500'}`}
                                    >
                                        {product.available
                                            ? 'Disponible'
                                            : 'Sin existencias'}
                                    </span>
                                </div>
                                <div className="flex flex-col gap-1 px-1">
                                    <p className="text-xs text-stone-500">
                                        {product.category ?? 'Colección'}
                                    </p>
                                    <h3 className="text-base font-medium wrap-break-word">
                                        {product.name}
                                    </h3>
                                    <div className="mt-1 flex items-center justify-between gap-2">
                                        <p className="text-sm">
                                            <span className="mr-1 text-stone-500">
                                                Desde
                                            </span>
                                            <span className="font-semibold">
                                                {money(product.price_from)}
                                            </span>
                                        </p>
                                        <ArrowRight className="size-4 text-teal-800 transition-transform group-hover:translate-x-1" />
                                    </div>
                                </div>
                            </Link>
                        ))}
                    </div>
                )}
                {products.last_page > 1 && (
                    <nav
                        aria-label="Páginas del catálogo"
                        className="flex items-center justify-center gap-5 border-t border-stone-200 pt-6"
                    >
                        {products.current_page > 1 && (
                            <Link
                                href={pageLink(products.current_page - 1)}
                                className="text-sm font-medium text-teal-900"
                            >
                                Anterior
                            </Link>
                        )}
                        <span className="text-sm text-stone-500">
                            {products.current_page} / {products.last_page}
                        </span>
                        {products.current_page < products.last_page && (
                            <Link
                                href={pageLink(products.current_page + 1)}
                                className="text-sm font-medium text-teal-900"
                            >
                                Siguiente
                            </Link>
                        )}
                    </nav>
                )}
            </section>
            {branches.length > 0 && (
                <section
                    className="mt-16 border-t border-stone-200 pt-10"
                    aria-labelledby="branches-title"
                >
                    <h2
                        id="branches-title"
                        className="mb-6 text-2xl font-medium tracking-tight"
                    >
                        Encuéntranos en nuestras sucursales
                    </h2>
                    <div className="grid gap-4 md:grid-cols-3">
                        {branches.map((branch) => (
                            <article
                                key={branch.id}
                                className="flex gap-3 rounded-2xl bg-stone-100 p-5"
                            >
                                <MapPin className="mt-0.5 size-5 shrink-0 text-teal-800" />
                                <div>
                                    <h3 className="font-medium">
                                        {branch.name}
                                    </h3>
                                    <p className="mt-1 text-sm leading-relaxed text-stone-500">
                                        {branch.address}
                                    </p>
                                </div>
                            </article>
                        ))}
                    </div>
                </section>
            )}
        </>
    );
}

function ShoppingIllustration() {
    return (
        <div className="flex size-36 items-center justify-center rounded-full border border-teal-900/15 bg-teal-900/5">
            <Package strokeWidth={1} className="size-20" />
        </div>
    );
}
