<?php

namespace App\Actions;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\ProductVariant;
use App\Models\Team;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BuildStoreReports
{
    /** @return EloquentBuilder<Order> */
    public function sales(Team $team, string $from, string $to, int $branchId = 0): EloquentBuilder
    {
        return Order::query()->where('team_id', $team->id)->where('status', OrderStatus::Confirmed)
            ->whereNotNull('completed_at')->where('completed_at', '>=', $from.' 00:00:00')
            ->where('completed_at', '<', CarbonImmutable::parse($to)->addDay()->startOfDay())
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId));
    }

    /** @return EloquentBuilder<OrderReturn> */
    public function returns(Team $team, string $from, string $to, int $branchId = 0): EloquentBuilder
    {
        return OrderReturn::query()->whereHas('order', fn ($query) => $query->where('team_id', $team->id)
            ->where('status', OrderStatus::Confirmed)->whereNotNull('completed_at')
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId)))
            ->where('created_at', '>=', $from.' 00:00:00')
            ->where('created_at', '<', CarbonImmutable::parse($to)->addDay()->startOfDay());
    }

    public function inventory(Team $team, int $branchId = 0): Builder
    {
        return ProductVariant::query()->toBase()
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->crossJoin('branches')
            ->leftJoin('stocks', function ($join): void {
                $join->on('stocks.product_variant_id', '=', 'product_variants.id')->on('stocks.branch_id', '=', 'branches.id')
                    ->on('stocks.team_id', '=', 'product_variants.team_id');
            })
            ->where('product_variants.team_id', $team->id)->where('products.team_id', $team->id)->where('branches.team_id', $team->id)
            ->where('product_variants.is_active', true)->where('products.is_active', true)->where('branches.is_active', true)
            ->when($branchId, fn ($query) => $query->where('branches.id', $branchId))
            ->select(['branches.id as branch_id', 'branches.name as branch', 'product_variants.id as variant_id', 'products.name as product', 'product_variants.name as variant', 'product_variants.sku'])
            ->selectRaw('COALESCE(stocks.quantity, 0) AS quantity');
    }

    public function topProducts(Team $team, string $from, string $to, int $branchId = 0): Builder
    {
        return DB::table('order_items')->whereIn('order_id', $this->sales($team, $from, $to, $branchId)->select('id'))
            ->select('product_variant_id as variant_id')
            ->selectRaw('MAX(product_name) AS product, MAX(variant_name) AS variant, MAX(sku) AS sku, SUM(quantity) AS units, SUM(quantity * unit_price_cents) AS amount_cents')
            ->groupBy('product_variant_id')->orderByDesc('units')->orderByDesc('amount_cents')->orderBy('product_variant_id');
    }

    /**
     * @param  Collection<int, \stdClass>  $sales
     * @param  Collection<int, \stdClass>  $refunds
     * @return list<array{from: string, to: string, sales_cents: int, refunds_cents: int, net_cents: int}>
     */
    public function trend(string $from, string $to, Collection $sales, Collection $refunds): array
    {
        $start = CarbonImmutable::parse($from)->startOfDay();
        $end = CarbonImmutable::parse($to)->startOfDay();
        $days = (int) $start->diffInDays($end) + 1;
        $interval = max(1, (int) ceil($days / 90));
        $buckets = [];
        for ($offset = 0; $offset < $days; $offset += $interval) {
            $bucketStart = $start->addDays($offset);
            $buckets[] = ['from' => $bucketStart->toDateString(), 'to' => $bucketStart->addDays($interval - 1)->min($end)->toDateString(),
                'sales_cents' => 0, 'refunds_cents' => 0, 'net_cents' => 0];
        }
        foreach (['sales_cents' => $sales, 'refunds_cents' => $refunds] as $key => $rows) {
            foreach ($rows as $row) {
                $index = (int) floor($start->diffInDays(CarbonImmutable::parse($row->date)) / $interval);
                if (isset($buckets[$index])) {
                    $buckets[$index][$key] += (int) $row->amount_cents;
                }
            }
        }
        foreach ($buckets as &$bucket) {
            $bucket['net_cents'] = $bucket['sales_cents'] - $bucket['refunds_cents'];
        }

        return $buckets;
    }

    /** @return array{sales_cents: int, refunds_cents: int, net_cents: int, orders: int, units_in_stock: int, out_of_stock: int} */
    public function summary(Team $team, string $from, string $to, int $branchId = 0): array
    {
        $sales = $this->sales($team, $from, $to, $branchId)->toBase()
            ->selectRaw('COUNT(*) AS orders, COALESCE(SUM(total_cents), 0) AS amount')->first();
        $refunds = (int) $this->returns($team, $from, $to, $branchId)->sum('amount_cents');
        $inventory = DB::query()->fromSub($this->inventory($team, $branchId), 'inventory')
            ->selectRaw('COALESCE(SUM(quantity), 0) AS units, COALESCE(SUM(CASE WHEN quantity = 0 THEN 1 ELSE 0 END), 0) AS empty_count')->first();

        return ['sales_cents' => (int) $sales->amount, 'refunds_cents' => $refunds, 'net_cents' => (int) $sales->amount - $refunds,
            'orders' => (int) $sales->orders, 'units_in_stock' => (int) $inventory->units, 'out_of_stock' => (int) $inventory->empty_count];
    }
}
