<?php

namespace App\Http\Controllers;

use App\Actions\BuildStoreReports;
use App\Http\Requests\BrowseReportsRequest;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(BrowseReportsRequest $request, Team $currentTeam, BuildStoreReports $reports): Response
    {
        $from = $request->string('from')->toString();
        $to = $request->string('to')->toString();
        $branchId = $request->integer('branch');
        $stock = $request->input('stock') ?: 'all';
        $dailySales = $reports->sales($currentTeam, $from, $to, $branchId)->toBase()
            ->selectRaw('DATE(completed_at) AS date, COUNT(*) AS orders, SUM(total_cents) AS amount_cents')
            ->groupByRaw('DATE(completed_at)')->orderBy('date')->get();
        $dailyRefunds = $reports->returns($currentTeam, $from, $to, $branchId)->toBase()
            ->selectRaw('DATE(created_at) AS date, SUM(amount_cents) AS amount_cents')
            ->groupByRaw('DATE(created_at)')->orderBy('date')->get();
        $stockByBranch = DB::query()->fromSub($reports->inventory($currentTeam, $branchId)
            ->when($stock === 'empty', fn ($query) => $query->whereRaw('COALESCE(stocks.quantity, 0) = 0')), 'inventory')
            ->select(['branch_id', 'branch'])->selectRaw('SUM(quantity) AS units, COUNT(*) AS variants, SUM(CASE WHEN quantity = 0 THEN 1 ELSE 0 END) AS empty_count')
            ->groupBy('branch_id', 'branch')->orderBy('branch')->get();
        $inventory = $reports->inventory($currentTeam, $branchId)
            ->when($stock === 'empty', fn ($query) => $query->whereRaw('COALESCE(stocks.quantity, 0) = 0'))
            ->orderBy('branches.name')->orderBy('products.name')->orderBy('product_variants.id')->orderBy('branches.id')
            ->paginate(24)->withQueryString();

        return Inertia::render('reports/index', [
            'summary' => $reports->summary($currentTeam, $from, $to, $branchId),
            'dailySales' => $dailySales,
            'chartTrend' => $reports->trend($from, $to, $dailySales, $dailyRefunds),
            'stockByBranch' => $stockByBranch,
            'topProducts' => $reports->topProducts($currentTeam, $from, $to, $branchId)->limit(10)->get(),
            'inventory' => $inventory,
            'branches' => $currentTeam->branches()->orderBy('name')->get(['id', 'name', 'is_active']),
            'filters' => ['from' => $from, 'to' => $to, 'branch' => $branchId ?: '', 'stock' => $stock, 'view' => $request->input('view') ?: 'details'],
        ]);
    }

    public function download(BrowseReportsRequest $request, Team $currentTeam, BuildStoreReports $reports): StreamedResponse
    {
        $from = $request->string('from')->toString();
        $to = $request->string('to')->toString();
        $branchId = $request->integer('branch');
        $type = $request->input('type') ?: 'sales';

        return response()->streamDownload(function () use ($request, $currentTeam, $reports, $from, $to, $branchId, $type): void {
            $output = fopen('php://output', 'w');
            if ($output === false) {
                throw new \RuntimeException('No se pudo generar el CSV.');
            }
            fwrite($output, "\xEF\xBB\xBF");
            $write = static function (array $row) use ($output): void {
                $row = array_map(static fn ($value) => is_string($value) && preg_match('/^[\s]*[=+\-@]/u', $value) ? "'".$value : $value, $row);
                fputcsv($output, $row, ',', '"', '');
            };
            if ($type === 'sales') {
                $write(['Pedido', 'Entregado el', 'Sucursal', 'Cliente', 'Pago', 'Venta bruta (Bs)']);
                foreach ($reports->sales($currentTeam, $from, $to, $branchId)->with('branch')->orderBy('id')->lazyById(200) as $order) {
                    $write([$order->id, $order->completed_at?->toDateTimeString(), $order->branch->name, $order->customer_name,
                        $order->payment_method?->value, number_format($order->total_cents / 100, 2, '.', '')]);
                }
            } elseif ($type === 'returns') {
                $write(['Devolución', 'Pedido', 'Registrada el', 'Sucursal', 'Producto', 'Variante', 'SKU', 'Unidades', 'Reembolso (Bs)']);
                foreach ($reports->returns($currentTeam, $from, $to, $branchId)->with(['order.branch', 'item'])->orderBy('id')->lazyById(200) as $return) {
                    $write([$return->id, $return->order_id, $return->created_at?->toDateTimeString(), $return->order->branch->name,
                        $return->item->getAttribute('product_name'), $return->item->getAttribute('variant_name'), $return->item->getAttribute('sku'),
                        $return->quantity, number_format($return->amount_cents / 100, 2, '.', '')]);
                }
            } elseif ($type === 'products') {
                $write(['Producto', 'Variante', 'SKU', 'Unidades vendidas', 'Venta bruta (Bs)']);
                foreach ($reports->topProducts($currentTeam, $from, $to, $branchId)->cursor() as $product) {
                    $write([$product->product, $product->variant, $product->sku, $product->units, number_format($product->amount_cents / 100, 2, '.', '')]);
                }
            } else {
                $write(['Sucursal', 'Producto', 'Variante', 'SKU', 'Existencias actuales']);
                $inventory = $reports->inventory($currentTeam, $branchId)
                    ->when($request->input('stock') === 'empty', fn ($query) => $query->whereRaw('COALESCE(stocks.quantity, 0) = 0'))
                    ->orderBy('branches.name')->orderBy('products.name')->orderBy('product_variants.id')->orderBy('branches.id');
                foreach ($inventory->cursor() as $row) {
                    $write([$row->branch, $row->product, $row->variant, $row->sku, $row->quantity]);
                }
            }
            fclose($output);
        }, "reporte-{$type}-{$from}-{$to}.csv", ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }
}
