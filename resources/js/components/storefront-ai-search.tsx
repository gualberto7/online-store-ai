import { Form, Link } from '@inertiajs/react';
import { ChevronDown, Sparkles } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { home } from '@/routes';

type Filters = {
    audience: string;
    q: string;
    ai_query: string;
    category: number | '';
    branch: number | '';
    available: boolean;
    sort: string;
};

export default function StorefrontAiSearch({
    filters,
    fallback,
}: {
    filters: Filters;
    fallback: boolean;
}) {
    const [open, setOpen] = useState(Boolean(filters.ai_query));
    const [description, setDescription] = useState(filters.ai_query);
    const clearQuery = home({
        query: {
            ...filters,
            ai_query: undefined,
            available: filters.available ? 1 : undefined,
            sort: filters.sort === 'relevance' ? 'name' : filters.sort,
        },
    });
    return (
        <Collapsible
            open={open}
            onOpenChange={setOpen}
            className="overflow-hidden rounded-2xl border border-teal-900/15 bg-teal-50/60"
        >
            <div className="flex flex-wrap items-center justify-between gap-4 p-4 sm:p-5">
                <div className="flex min-w-0 items-center gap-3">
                    <span className="flex size-10 shrink-0 items-center justify-center rounded-full bg-teal-900 text-white">
                        <Sparkles className="size-5" aria-hidden="true" />
                    </span>
                    <div>
                        <p className="text-sm font-medium text-teal-950">
                            ¿Buscas algo especial?
                        </p>
                        <p className="text-sm text-teal-900/70">
                            Describe tu idea y encuentra opciones con IA.
                        </p>
                    </div>
                </div>
                <CollapsibleTrigger asChild>
                    <Button
                        type="button"
                        variant="outline"
                        className="gap-2 border-teal-900/20 bg-white text-teal-950 hover:bg-teal-100"
                    >
                        <Sparkles className="size-4" aria-hidden="true" />
                        {open ? 'Ocultar consulta de IA' : 'Consultar con IA'}
                        <ChevronDown
                            className={`size-4 transition-transform ${open ? 'rotate-180' : ''}`}
                            aria-hidden="true"
                        />
                    </Button>
                </CollapsibleTrigger>
            </div>
            {!open && filters.ai_query && (
                <p className="px-5 pb-4 text-sm text-teal-900">
                    Consulta activa:{' '}
                    <span className="font-medium">{filters.ai_query}</span>
                </p>
            )}
            <CollapsibleContent>
                <Form
                    {...home.form()}
                    className="flex flex-col gap-4 border-t border-teal-900/10 p-4 sm:p-5"
                >
                    {({ errors, processing }) => (
                        <>
                            <input
                                type="hidden"
                                name="audience"
                                value={filters.audience}
                            />
                            <input type="hidden" name="q" value={filters.q} />
                            <input
                                type="hidden"
                                name="category"
                                value={filters.category}
                            />
                            <input
                                type="hidden"
                                name="branch"
                                value={filters.branch}
                            />
                            <input
                                type="hidden"
                                name="available"
                                value={filters.available ? 1 : 0}
                            />
                            <input
                                type="hidden"
                                name="sort"
                                value="relevance"
                            />
                            <div className="flex flex-col gap-2">
                                <label
                                    htmlFor="ai-query"
                                    className="text-sm font-medium text-teal-950"
                                >
                                    Describe lo que te gustaría comprar
                                </label>
                                <textarea
                                    id="ai-query"
                                    name="ai_query"
                                    rows={4}
                                    required
                                    maxLength={1000}
                                    value={description}
                                    onChange={(event) =>
                                        setDescription(event.target.value)
                                    }
                                    placeholder="Por ejemplo: busco un regalo para alguien que disfruta del café y los accesorios prácticos."
                                    aria-describedby="ai-query-help"
                                    aria-invalid={Boolean(errors.ai_query)}
                                    className="min-h-28 w-full resize-y rounded-xl border border-teal-900/20 bg-white px-4 py-3 text-sm leading-relaxed text-stone-900 placeholder:text-stone-400 focus-visible:ring-2 focus-visible:ring-teal-700 focus-visible:outline-none"
                                />
                                <div
                                    id="ai-query-help"
                                    className="flex items-start justify-between gap-4 text-xs text-teal-900/70"
                                >
                                    <span>
                                        Cuéntanos para quién es, la ocasión o el
                                        uso que tienes en mente.
                                    </span>
                                    <span className="shrink-0">
                                        {description.length}/1000
                                    </span>
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
                            {fallback && (
                                <p
                                    role="status"
                                    className="text-sm text-stone-600"
                                >
                                    La consulta con IA no está disponible en
                                    este momento. Mostramos coincidencias por
                                    texto.
                                </p>
                            )}
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <p className="text-xs text-teal-900/70">
                                    Se aplican la categoría, sucursal y
                                    disponibilidad seleccionadas.
                                </p>
                                <div className="flex items-center gap-4">
                                    {filters.ai_query && (
                                        <Link
                                            href={clearQuery}
                                            className="text-sm text-teal-900 underline underline-offset-4"
                                        >
                                            Quitar consulta
                                        </Link>
                                    )}
                                    <Button
                                        type="submit"
                                        disabled={
                                            processing || !description.trim()
                                        }
                                        className="gap-2 bg-teal-900 text-white hover:bg-teal-950"
                                    >
                                        <Sparkles
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        {processing
                                            ? 'Buscando ideas…'
                                            : 'Encontrar opciones con IA'}
                                    </Button>
                                </div>
                            </div>
                        </>
                    )}
                </Form>
            </CollapsibleContent>
        </Collapsible>
    );
}
