import { Link, usePage } from '@inertiajs/react';
import { ArrowUpRight, ShoppingBag } from 'lucide-react';
import type { PropsWithChildren } from 'react';
import { dashboard, home, login } from '@/routes';
import { index as cartIndex } from '@/routes/storefront/cart';

export default function StorefrontLayout({ children }: PropsWithChildren) {
    const { storefront, auth, currentTeam, cartCount } = usePage<{
        storefront: { name: string };
        cartCount: number;
    }>().props;
    return (
        <div className="flex min-h-screen flex-col bg-stone-50 text-stone-900 [color-scheme:light]">
            <div className="bg-teal-950 px-4 py-2 text-center text-xs tracking-wide text-teal-50">
                Encuentra tu favorito · Consulta disponibilidad por sucursal
            </div>
            <header className="border-b border-stone-200 bg-stone-50">
                <div className="mx-auto flex max-w-7xl items-center justify-between gap-4 px-5 py-5 sm:px-8">
                    <Link
                        href={home()}
                        className="flex items-center gap-3 font-semibold tracking-tight"
                    >
                        <span className="flex size-10 items-center justify-center rounded-full bg-teal-900 text-white">
                            <ShoppingBag className="size-5" />
                        </span>
                        <span className="text-xl">{storefront.name}</span>
                    </Link>
                    <nav
                        aria-label="Navegación de la tienda"
                        className="flex items-center gap-5 text-sm"
                    >
                        <Link
                            href={home()}
                            className="font-medium underline-offset-4 hover:underline"
                        >
                            Catálogo
                        </Link>
                        <Link
                            href={cartIndex()}
                            className="font-medium text-teal-900"
                        >
                            Carrito ({cartCount})
                        </Link>
                        <Link
                            href={
                                auth.user && currentTeam
                                    ? dashboard(currentTeam.slug)
                                    : login()
                            }
                            className="hidden items-center gap-1 text-stone-500 hover:text-stone-900 sm:flex"
                        >
                            Acceso al panel
                            <ArrowUpRight className="size-3.5" />
                        </Link>
                    </nav>
                </div>
            </header>
            <main className="mx-auto w-full max-w-7xl flex-1 px-5 py-8 sm:px-8 sm:py-12">
                {children}
            </main>
            <footer className="border-t border-stone-200">
                <div className="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-5 py-7 text-sm text-stone-500 sm:px-8">
                    <span className="font-medium text-stone-800">
                        {storefront.name}
                    </span>
                    <span>
                        Todos los precios están expresados en bolivianos (Bs).
                    </span>
                    <Link
                        href={login()}
                        className="underline-offset-4 hover:underline sm:hidden"
                    >
                        Acceso al panel
                    </Link>
                </div>
            </footer>
        </div>
    );
}
