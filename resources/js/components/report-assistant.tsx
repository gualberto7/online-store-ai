import { useHttp } from '@inertiajs/react';
import { LoaderCircle, Sparkles, Table2, ChartColumn } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import InputError from '@/components/input-error';
import { reportMoney } from '@/components/report-summary';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { ai } from '@/routes/reports';

type ReportData = {
    title: string;
    from: string;
    to: string;
    branch: string;
    notes: string;
    limit: number;
    columns: {
        key: string;
        label: string;
        format: 'money' | 'number' | 'text';
    }[];
    rows: Record<string, string | number>[];
};
type Answer = { answer: string; reports: ReportData[] };
type Query = {
    prompt: string;
    from: string;
    to: string;
    branch: number | '';
    stock: string;
};

function Result({ report }: { report: ReportData }) {
    const [view, setView] = useState<'table' | 'chart'>('table');
    const numeric = report.columns.filter((column) => column.format !== 'text');
    const label = report.columns.find((column) => column.format === 'text');
    const [metric, setMetric] = useState(numeric[0]?.key ?? '');
    const selectedMetric = numeric.find((column) => column.key === metric);
    const hasChart = Boolean(
        label &&
        selectedMetric &&
        report.rows.length > 1 &&
        report.rows.every((row) => Number(row[metric]) >= 0),
    );
    const max = Math.max(
        1,
        ...report.rows.map((row) => Number(row[metric] ?? 0)),
    );
    const format = (value: string | number, kind: string) =>
        kind === 'money'
            ? reportMoney(value)
            : kind === 'number'
              ? Number(value).toLocaleString('es-BO')
              : value;
    return (
        <section className="min-w-0 overflow-hidden rounded-xl border bg-card text-card-foreground">
            <div className="grid gap-3 border-b p-5">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 className="font-semibold">{report.title}</h3>
                        <p className="text-sm text-muted-foreground">
                            {report.from} al {report.to} · {report.branch}
                        </p>
                    </div>
                    {hasChart && (
                        <div className="flex gap-2">
                            <Button
                                type="button"
                                size="sm"
                                variant={
                                    view === 'table' ? 'default' : 'outline'
                                }
                                onClick={() => setView('table')}
                                aria-pressed={view === 'table'}
                            >
                                <Table2 className="size-4" aria-hidden="true" />
                                Tabla
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                variant={
                                    view === 'chart' ? 'default' : 'outline'
                                }
                                onClick={() => setView('chart')}
                                aria-pressed={view === 'chart'}
                            >
                                <ChartColumn
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Gráfica
                            </Button>
                        </div>
                    )}
                </div>
                <p className="text-sm text-muted-foreground">
                    {report.notes.replace(
                        ' Importes en centavos de boliviano.',
                        '',
                    )}{' '}
                    Se muestran {report.rows.length} filas.
                </p>
            </div>
            {report.rows.length === 0 ? (
                <p className="p-6 text-muted-foreground">
                    No hay registros para esta consulta. Revisa el período o la
                    sucursal.
                </p>
            ) : view === 'chart' && hasChart && label && selectedMetric ? (
                <div className="grid gap-5 p-5">
                    <label className="flex flex-wrap items-center gap-2 text-sm">
                        Medida
                        <select
                            value={metric}
                            onChange={(event) => setMetric(event.target.value)}
                            className="h-9 rounded-md border border-input bg-background px-3"
                        >
                            {numeric.map((column) => (
                                <option key={column.key} value={column.key}>
                                    {column.label}
                                    {column.format === 'money' ? ' (Bs)' : ''}
                                </option>
                            ))}
                        </select>
                    </label>
                    <ol className="grid max-h-96 gap-4 overflow-y-auto">
                        {report.rows.map((row, index) => (
                            <li key={index} className="grid gap-2">
                                <div className="flex justify-between gap-4 text-sm">
                                    <span className="break-words">
                                        {row[label.key]}
                                    </span>
                                    <span className="shrink-0 tabular-nums">
                                        {format(
                                            row[metric],
                                            selectedMetric.format,
                                        )}
                                    </span>
                                </div>
                                <div
                                    role="img"
                                    aria-label={`${row[label.key]}: ${format(row[metric], selectedMetric.format)}`}
                                    className="h-3 rounded-full bg-muted"
                                >
                                    <div
                                        className="h-full rounded-full bg-sky-600 dark:bg-sky-400"
                                        style={{
                                            width: `${(Number(row[metric]) / max) * 100}%`,
                                        }}
                                    />
                                </div>
                            </li>
                        ))}
                    </ol>
                </div>
            ) : (
                <div className="max-h-[32rem] overflow-auto">
                    <table className="w-full text-sm">
                        <thead className="sticky top-0 bg-muted">
                            <tr>
                                {report.columns.map((column) => (
                                    <th
                                        key={column.key}
                                        className="px-4 py-3 text-left"
                                    >
                                        {column.label}
                                        {column.format === 'money'
                                            ? ' (Bs)'
                                            : ''}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {report.rows.map((row, index) => (
                                <tr key={index} className="border-t">
                                    {report.columns.map((column) => (
                                        <td
                                            key={column.key}
                                            className={`px-4 py-3 ${column.format === 'text' ? '' : 'whitespace-nowrap tabular-nums'}`}
                                        >
                                            {format(
                                                row[column.key],
                                                column.format,
                                            )}
                                        </td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </section>
    );
}

export default function ReportAssistant({
    team,
    filters,
    available,
}: {
    team: string;
    filters: { from: string; to: string; branch: number | ''; stock: string };
    available: boolean;
}) {
    const form = useHttp<Query, Answer>({
        prompt: '',
        from: filters.from,
        to: filters.to,
        branch: filters.branch,
        stock: filters.stock,
    });
    const [failure, setFailure] = useState('');
    const [answer, setAnswer] = useState<Answer | null>(null);
    const [question, setQuestion] = useState('');
    const examples = [
        'Muéstrame los 10 clientes que más compraron este mes',
        'Los 10 productos más vendidos en el período, ordenados por unidades',
        'Compara las ventas de este mes con las del mes pasado',
        '¿Qué productos están agotados por sucursal?',
    ];
    async function submit(event: FormEvent) {
        event.preventDefault();
        setFailure('');
        setAnswer(null);
        setQuestion(form.data.prompt);
        try {
            const result = await form.post(ai.url(team), {
                onHttpException: (response) => {
                    let data: unknown;
                    try {
                        data = JSON.parse(response.data);
                    } catch {
                        data = null;
                    }
                    const message =
                        data &&
                        typeof data === 'object' &&
                        'message' in data &&
                        typeof data.message === 'string'
                            ? data.message
                            : 'No se pudo completar la consulta.';
                    setFailure(
                        response.status === 429
                            ? 'Puedes hacer hasta cinco consultas por minuto. Espera un momento y vuelve a intentar.'
                            : message,
                    );
                    return false;
                },
                onNetworkError: () => {
                    setFailure(
                        'No se pudo conectar con el servidor. Revisa tu conexión e intenta nuevamente.',
                    );
                    return false;
                },
            });
            setAnswer(result);
        } catch {
            setFailure(
                (current) =>
                    current ||
                    'No se pudo completar la consulta. Revisa los campos e intenta nuevamente.',
            );
        }
    }
    return (
        <section
            className="grid min-w-0 gap-5"
            aria-labelledby="report-ai-title"
        >
            <div className="rounded-xl border bg-card p-5 text-card-foreground">
                <div className="flex items-center gap-2">
                    <Sparkles
                        className="size-5 text-sky-600 dark:text-sky-400"
                        aria-hidden="true"
                    />
                    <h2 id="report-ai-title" className="font-semibold">
                        Consulta tus reportes con IA
                    </h2>
                </div>
                <p className="mt-2 text-sm text-muted-foreground">
                    Pregunta por ventas, clientes, variantes, devoluciones o
                    inventario. Indica en tu pregunta el período y la sucursal
                    que quieres consultar. Cada pregunta es independiente.
                </p>
                {!available && (
                    <p
                        className="mt-3 rounded-md border p-3 text-sm"
                        role="status"
                    >
                        La consulta con IA necesita una credencial configurada
                        para el proveedor de reportes.
                    </p>
                )}
                <div className="mt-4 flex flex-wrap gap-2">
                    {examples.map((example) => (
                        <Button
                            type="button"
                            key={example}
                            variant="outline"
                            className="h-auto max-w-full py-2 text-left whitespace-normal"
                            disabled={form.processing}
                            onClick={() => form.setData('prompt', example)}
                        >
                            {example}
                        </Button>
                    ))}
                </div>
                <form onSubmit={submit} className="mt-5 grid gap-3">
                    <Label htmlFor="report-ai-prompt">
                        ¿Qué quieres consultar?
                    </Label>
                    <textarea
                        id="report-ai-prompt"
                        value={form.data.prompt}
                        onChange={(event) =>
                            form.setData('prompt', event.target.value)
                        }
                        required
                        maxLength={2000}
                        rows={4}
                        disabled={form.processing}
                        aria-invalid={!!form.errors.prompt}
                        placeholder="Por ejemplo: muéstrame los cinco productos con mayor importe vendido en octubre"
                        className="w-full rounded-md border border-input bg-background p-3 text-sm focus-visible:ring-2 focus-visible:ring-ring"
                    />
                    <InputError message={form.errors.prompt} />
                    <InputError
                        message={
                            form.errors.from ||
                            form.errors.to ||
                            form.errors.branch
                        }
                    />
                    <p className="text-xs text-muted-foreground">
                        La consulta consume saldo del proveedor de IA. Se envían
                        tu pregunta y los resultados necesarios para responder;
                        los contactos de clientes se muestran protegidos.
                    </p>
                    <div className="flex items-center justify-between gap-3">
                        <span className="text-xs text-muted-foreground">
                            {form.data.prompt.length}/2000
                        </span>
                        <Button
                            disabled={
                                !available ||
                                form.processing ||
                                !form.data.prompt.trim()
                            }
                        >
                            {form.processing ? (
                                <>
                                    <LoaderCircle
                                        className="size-4 animate-spin"
                                        aria-hidden="true"
                                    />
                                    Consultando datos…
                                </>
                            ) : (
                                <>
                                    <Sparkles
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Consultar con IA
                                </>
                            )}
                        </Button>
                    </div>
                </form>
                {failure && (
                    <p role="alert" className="mt-4 text-sm text-destructive">
                        {failure}
                    </p>
                )}
            </div>
            {form.processing && (
                <p
                    role="status"
                    className="animate-pulse text-sm text-muted-foreground"
                >
                    La IA está consultando los reportes. Esto puede tomar unos
                    segundos.
                </p>
            )}
            {answer && (
                <>
                    <article className="grid gap-3 rounded-xl border p-5">
                        <p className="text-sm font-medium">{question}</p>
                        <h3 className="flex items-center gap-2 font-semibold">
                            <Sparkles className="size-4" aria-hidden="true" />
                            Respuesta
                        </h3>
                        <p className="text-sm leading-relaxed whitespace-pre-wrap">
                            {answer.answer}
                        </p>
                        {answer.reports.length > 0 && (
                            <p className="text-xs text-muted-foreground">
                                Las tablas siguientes provienen de consultas
                                calculadas por el servidor.
                            </p>
                        )}
                    </article>
                    {answer.reports.map((report, index) => (
                        <Result key={`${question}-${index}`} report={report} />
                    ))}
                </>
            )}
        </section>
    );
}
