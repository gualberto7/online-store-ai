<?php

namespace App\Ai\Tools;

use App\Actions\BuildStoreReports;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class QueryStoreReports implements Tool
{
    /** @var list<array<string, mixed>> */
    public array $reports = [];

    public function __construct(private Team $team, private User $user, private string $from, private string $to, private int $branchId = 0, private bool $onlyEmpty = false) {}

    public function description(): string
    {
        return 'Consulta datos reales de la empresa actual. Reportes: sales_summary, top_customers (por identificador, no empleados), top_products (variantes), sales_by_branch, sales_timeline, returns (por variante), inventory (actual). Los rankings permiten sort=amount/units/orders/name/stock, direction=asc/desc, limit=1..50 y search por nombre o SKU. Las fechas son inclusivas; branch_id=0 significa todas. No ejecuta SQL proporcionado por el usuario.';
    }

    public function handle(Request $request): string
    {
        Gate::forUser($this->user)->authorize('viewReports', $this->team);
        if (count($this->reports) >= 4) {
            return json_encode(['error' => 'Se alcanzó el límite de cuatro reportes por consulta.'], JSON_THROW_ON_ERROR);
        }
        try {
            $data = Validator::make([
                ...$request->all(),
                'from' => $request['from'] ?? $this->from,
                'to' => $request['to'] ?? $this->to,
                'branch_id' => $request['branch_id'] ?? $this->branchId,
            ], [
                'report' => ['required', Rule::in(['sales_summary', 'top_customers', 'top_products', 'sales_by_branch', 'sales_timeline', 'returns', 'inventory'])],
                'from' => ['required', 'date_format:Y-m-d'], 'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
                'branch_id' => ['required', 'integer', 'min:0', Rule::when(($request['branch_id'] ?? $this->branchId) != 0, Rule::exists('branches', 'id')->where('team_id', $this->team->id))],
                'sort' => ['sometimes', Rule::in(['amount', 'units', 'orders', 'name', 'stock'])],
                'direction' => ['sometimes', Rule::in(['asc', 'desc'])], 'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
                'search' => ['sometimes', 'nullable', 'string', 'max:120'], 'only_empty' => ['sometimes', 'boolean'],
                'sql' => ['prohibited'], 'team_id' => ['prohibited'], 'user_id' => ['prohibited'],
            ])->validate();
            if (CarbonImmutable::parse($data['from'])->diffInDays(CarbonImmutable::parse($data['to'])) > 731) {
                return json_encode(['error' => 'Consulta como máximo dos años por reporte.'], JSON_THROW_ON_ERROR);
            }
        } catch (ValidationException $exception) {
            return json_encode(['error' => 'Parámetros inválidos. Corrige la llamada.', 'details' => $exception->errors()], JSON_THROW_ON_ERROR);
        }
        $from = $data['from'];
        $to = $data['to'];
        $branch = (int) $data['branch_id'];
        $sort = $data['sort'] ?? ($data['report'] === 'top_products' ? 'units' : ($data['report'] === 'inventory' ? 'stock' : 'amount'));
        $direction = $data['direction'] ?? 'desc';
        $limit = (int) ($data['limit'] ?? 10);
        $search = trim($data['search'] ?? '');
        $builder = app(BuildStoreReports::class);
        $sales = $builder->sales($this->team, $from, $to, $branch);
        $notes = 'Ventas de pedidos pagados y entregados, antes de devoluciones. Importes en centavos de boliviano.';
        switch ($data['report']) {
            case 'sales_summary':
                $title = 'Resumen de ventas';
                $columns = ['sales_cents' => 'Ventas brutas', 'refunds_cents' => 'Reembolsos', 'net_cents' => 'Neto', 'orders' => 'Pedidos', 'units_in_stock' => 'Stock actual', 'out_of_stock' => 'Variantes/sucursal agotadas'];
                $rows = [$builder->summary($this->team, $from, $to, $branch)];
                $notes = 'Ventas por fecha de entrega; reembolsos por su fecha de registro, aunque la venta sea anterior. Neto=ventas-reembolsos, no utilidad. Stock actual, independiente de las fechas.';
                break;
            case 'top_customers':
                $title = 'Clientes que más compraron';
                $columns = ['customer' => 'Cliente', 'identifier' => 'Identificador protegido', 'orders' => 'Pedidos', 'amount_cents' => 'Compras brutas'];
                $email = "LOWER(TRIM(COALESCE(customer_email, '')))";
                $phone = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(COALESCE(customer_phone, '')), ' ', ''), '-', ''), '(', ''), ')', ''), '+', '')";
                $identity = "CASE WHEN {$email} <> '' THEN 'email:' || {$email} ELSE 'phone:' || {$phone} END";
                $missing = (clone $sales)->whereRaw("{$email} = '' AND {$phone} = ''")->count();
                $rank = $sales->toBase()->whereRaw("({$email} <> '' OR {$phone} <> '')")
                    ->when($search !== '', fn ($query) => $query->whereLike('customer_name', "%{$search}%"))
                    ->selectRaw("{$identity} AS identity, MAX(customer_name) AS customer, COUNT(*) AS orders, SUM(total_cents) AS amount_cents")
                    ->groupByRaw($identity)->orderBy($sort === 'orders' ? 'orders' : ($sort === 'name' ? 'customer' : 'amount_cents'), $direction)->orderBy('identity')->limit($limit)->get();
                $rows = $rank->map(function ($row): array {
                    $value = explode(':', $row->identity, 2)[1];
                    $identifier = str_starts_with($row->identity, 'email:')
                        ? mb_substr($value, 0, 1).'***@'.Str::after($value, '@') : '***'.mb_substr($value, -4);

                    return ['customer' => $row->customer ?: 'Sin nombre', 'identifier' => $identifier, 'orders' => (int) $row->orders, 'amount_cents' => (int) $row->amount_cents];
                })->all();
                $notes .= " Se agrupa por correo normalizado o, si falta, teléfono. No por nombre ni usuario empleado. Pedidos excluidos por falta de identificador: {$missing}. Un mismo cliente con identificadores distintos puede aparecer separado.";
                break;
            case 'top_products':
                $title = 'Productos y variantes más vendidos';
                $columns = ['product' => 'Producto', 'variant' => 'Variante', 'sku' => 'SKU', 'units' => 'Unidades', 'amount_cents' => 'Venta bruta'];
                $query = $builder->topProducts($this->team, $from, $to, $branch);
                if ($search !== '') {
                    $query->where(fn ($query) => $query->whereLike('product_name', "%{$search}%")->orWhereLike('sku', "%{$search}%"));
                }
                $rows = $query->reorder()->orderBy($sort === 'units' ? 'units' : ($sort === 'name' ? 'product' : 'amount_cents'), $direction)->orderBy('product_variant_id')->limit($limit)->get()->map(fn ($row) => (array) $row)->all();
                break;
            case 'sales_by_branch':
                $title = 'Ventas por sucursal';
                $columns = ['branch' => 'Sucursal', 'orders' => 'Pedidos', 'amount_cents' => 'Venta bruta'];
                $rows = DB::query()->fromSub($sales->select(['branch_id', 'total_cents'])->toBase(), 'sales')->join('branches', 'branches.id', '=', 'sales.branch_id')
                    ->selectRaw('branches.name AS branch, COUNT(*) AS orders, SUM(sales.total_cents) AS amount_cents')
                    ->groupBy('branches.id', 'branches.name')->orderBy($sort === 'orders' ? 'orders' : ($sort === 'name' ? 'branch' : 'amount_cents'), $direction)->orderBy('branches.id')->limit($limit)->get()->map(fn ($row) => (array) $row)->all();
                break;
            case 'sales_timeline':
                $title = 'Evolución de ventas y devoluciones';
                $columns = ['from' => 'Desde', 'to' => 'Hasta', 'sales_cents' => 'Ventas', 'refunds_cents' => 'Reembolsos', 'net_cents' => 'Neto'];
                $dailySales = $sales->toBase()->selectRaw('DATE(completed_at) AS date, SUM(total_cents) AS amount_cents')->groupByRaw('DATE(completed_at)')->get();
                $dailyReturns = $builder->returns($this->team, $from, $to, $branch)->toBase()->selectRaw('DATE(created_at) AS date, SUM(amount_cents) AS amount_cents')->groupByRaw('DATE(created_at)')->get();
                $rows = $builder->trend($from, $to, $dailySales, $dailyReturns);
                $notes = 'Intervalos cronológicos con días sin movimientos en cero. Máximo 90 intervalos. Neto=ventas entregadas menos reembolsos registrados en el intervalo, no utilidad.';
                break;
            case 'returns':
                $title = 'Devoluciones por variante';
                $columns = ['product' => 'Producto', 'sku' => 'SKU', 'units' => 'Unidades devueltas', 'amount_cents' => 'Reembolso'];
                $rows = DB::query()->fromSub($builder->returns($this->team, $from, $to, $branch)->select(['order_item_id', 'quantity', 'amount_cents'])->toBase(), 'refunds')->join('order_items', 'order_items.id', '=', 'refunds.order_item_id')
                    ->selectRaw('MAX(order_items.product_name) AS product, MAX(order_items.sku) AS sku, SUM(refunds.quantity) AS units, SUM(refunds.amount_cents) AS amount_cents')
                    ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query->whereLike('order_items.product_name', "%{$search}%")->orWhereLike('order_items.sku', "%{$search}%")))
                    ->groupBy('order_items.product_variant_id')->orderBy($sort === 'units' ? 'units' : 'amount_cents', $direction)->orderBy('order_items.product_variant_id')->limit($limit)->get()->map(fn ($row) => (array) $row)->all();
                $notes = 'Reembolsos registrados en el período, incluidos los de ventas de fechas anteriores.';
                break;
            default:
                $title = 'Inventario actual';
                $columns = ['branch' => 'Sucursal', 'product' => 'Producto', 'variant' => 'Variante', 'sku' => 'SKU', 'quantity' => 'Existencias'];
                $rows = $builder->inventory($this->team, $branch)
                    ->when($data['only_empty'] ?? $this->onlyEmpty, fn ($query) => $query->whereRaw('COALESCE(stocks.quantity, 0) = 0'))
                    ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query->whereLike('products.name', "%{$search}%")->orWhereLike('product_variants.sku', "%{$search}%")))
                    ->orderBy($sort === 'name' ? 'products.name' : 'quantity', $direction)->orderBy('branches.id')->orderBy('product_variants.id')->limit($limit)->get()->map(fn ($row) => (array) $row)->all();
                $notes = 'Existencias actuales de productos, variantes y sucursales activos. Incluye saldos cero aunque no haya una entrada. Las fechas no modifican el inventario.';
        }
        if (! in_array($data['report'], ['sales_summary', 'sales_timeline'], true)) {
            $notes .= " Máximo {$limit} filas; no representa necesariamente todos los registros.";
        }
        $numeric = ['orders', 'units', 'quantity', 'units_in_stock', 'out_of_stock'];
        $columnData = [];
        foreach ($columns as $key => $label) {
            $columnData[] = ['key' => $key, 'label' => $label, 'format' => str_ends_with($key, '_cents') ? 'money' : (in_array($key, $numeric, true) ? 'number' : 'text')];
        }
        $formattedRows = [];
        foreach ($rows as $row) {
            $formatted = [];
            foreach ($columnData as $column) {
                $value = $row[$column['key']] ?? '';
                $formatted[$column['key']] = $column['format'] === 'text' ? mb_substr((string) $value, 0, 255) : (int) $value;
            }
            $formattedRows[] = $formatted;
        }
        $result = ['title' => $title, 'from' => $from, 'to' => $to, 'branch' => $branch === 0 ? 'Todas las sucursales' : $this->team->branches()->whereKey($branch)->value('name'),
            'columns' => $columnData, 'rows' => $formattedRows, 'notes' => $notes, 'limit' => $limit, 'sort' => $sort, 'direction' => $direction];
        $this->reports[] = $result;

        return json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'report' => $schema->string()->enum(['sales_summary', 'top_customers', 'top_products', 'sales_by_branch', 'sales_timeline', 'returns', 'inventory'])->required(),
            'from' => $schema->string()->description('Fecha inicial YYYY-MM-DD. Omite para usar los filtros actuales.'),
            'to' => $schema->string()->description('Fecha final YYYY-MM-DD inclusive.'),
            'branch_id' => $schema->integer()->description('ID de sucursal de esta empresa; 0=todas. Omite para usar el filtro actual.'),
            'sort' => $schema->string()->enum(['amount', 'units', 'orders', 'name', 'stock']),
            'direction' => $schema->string()->enum(['asc', 'desc']),
            'limit' => $schema->integer()->min(1)->max(50)->description('Máximo de filas para rankings; la evolución devuelve hasta 90 intervalos.'),
            'search' => $schema->string()->description('Filtro opcional por nombre de cliente, producto o SKU.'),
            'only_empty' => $schema->boolean()->description('Solo saldos cero en inventario.'),
        ];
    }
}
