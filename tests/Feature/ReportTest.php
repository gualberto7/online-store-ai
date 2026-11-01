<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\TeamRole;
use App\Models\Branch;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Stock;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['semantic-search.enabled' => false]);
        $this->travelTo(now()->setDate(2026, 11, 15)->setTime(12, 0));
    }

    public function test_guests_cannot_open_or_export_reports(): void
    {
        $team = Team::factory()->create();

        $this->get(route('reports.index', $team))->assertRedirect(route('login'));
        $this->get(route('reports.export', $team))->assertRedirect(route('login'));
    }

    #[TestWith(['owner', true])]
    #[TestWith(['admin', true])]
    #[TestWith(['member', false])]
    public function test_report_permissions_apply_to_pages_exports_and_dashboard(string $role, bool $allowed): void
    {
        [$user, $team] = $this->member(TeamRole::from($role));

        $response = $this->actingAs($user)->get(route('reports.index', $team));
        $download = $this->get(route('reports.export', $team));
        if ($allowed) {
            $response->assertInertia(fn (Assert $page) => $page->component('reports/index')->where('summary.sales_cents', 0)->where('filters.from', '2026-11-01')->where('filters.to', '2026-11-15'));
            $download->assertDownload('reporte-sales-2026-11-01-2026-11-15.csv');
            $this->get(route('dashboard', $team))->assertInertia(fn (Assert $page) => $page->where('reportSummary.net_cents', 0));
        } else {
            $response->assertForbidden();
            $download->assertForbidden();
            $this->get(route('dashboard', $team))->assertInertia(fn (Assert $page) => $page->where('reportSummary', null));
        }
    }

    public function test_foreign_and_personal_teams_cannot_be_used_for_reports(): void
    {
        [$user, $team] = $this->member();
        $foreign = Team::factory()->create();
        $personal = Team::factory()->personal()->create();
        $personal->members()->attach($user, ['role' => TeamRole::Owner->value]);

        $this->actingAs($user)->get(route('reports.index', $foreign))->assertForbidden();
        $this->get(route('reports.export', $foreign))->assertForbidden();
        $this->get(route('reports.index', $personal))->assertForbidden();
        $this->get(route('reports.export', $personal))->assertForbidden();
    }

    public function test_sales_and_refunds_use_their_own_dates_and_preserve_historical_prices(): void
    {
        [$user, $team] = $this->member();
        $branch = Branch::factory()->for($team)->create();
        $variant = ProductVariant::factory()->for(Product::factory()->for($team))->create(['price' => '999.99']);
        $sale = $this->sale($branch, $variant, '2026-11-01 00:00:00', 3, 4000);
        $sale->forceFill(['created_at' => '2026-10-01 00:00:00'])->save();
        $endSale = $this->sale($branch, $variant, '2026-11-15 23:59:59', 2, 3000);
        $oldSale = $this->sale($branch, $variant, '2026-10-31 23:59:59', 1, 1500);
        $this->sale($branch, $variant, '2026-11-16 00:00:00', 1, 10000);
        Order::factory()->for($branch)->create(['total_cents' => 20000, 'status' => OrderStatus::Confirmed]);
        Order::factory()->for($branch)->create(['total_cents' => 30000, 'status' => OrderStatus::Draft]);
        Order::factory()->for($branch)->create(['total_cents' => 40000, 'status' => OrderStatus::Cancelled, 'completed_at' => now()]);
        $this->refund($oldSale, 1500, '2026-11-15 10:00:00');
        $this->refund($endSale, 1000, '2026-11-15 23:59:59');
        $this->refund($sale, 1000, '2026-11-16 00:00:00');
        $foreignBranch = Branch::factory()->create();
        $foreignVariant = ProductVariant::factory()->for(Product::factory()->create(['team_id' => $foreignBranch->team_id]))->create();
        $foreign = $this->sale($foreignBranch, $foreignVariant, '2026-11-10 12:00:00', 1, 50000);
        $this->refund($foreign, 50000, '2026-11-15 10:00:00');

        $this->actingAs($user)->get(route('reports.index', $team))->assertInertia(fn (Assert $page) => $page
            ->where('summary.sales_cents', 18000)->where('summary.refunds_cents', 2500)->where('summary.net_cents', 15500)->where('summary.orders', 2)
            ->has('dailySales', 2)->where('dailySales.0.date', '2026-11-01')->where('dailySales.1.date', '2026-11-15')
            ->has('chartTrend', 15)->where('chartTrend.0.sales_cents', 12000)->where('chartTrend.1.sales_cents', 0)
            ->where('chartTrend.14.refunds_cents', 2500)->where('chartTrend.14.net_cents', 3500)
            ->has('topProducts', 1)->where('topProducts.0.units', fn ($units) => (int) $units === 5)
            ->where('topProducts.0.amount_cents', fn ($amount) => (int) $amount === 18000));
        $this->get(route('dashboard', $team))->assertInertia(fn (Assert $page) => $page->where('reportSummary.net_cents', 15500));
    }

    public function test_refunds_can_make_net_negative_without_any_sales_in_the_period(): void
    {
        [$user, $team] = $this->member();
        $branch = Branch::factory()->for($team)->create();
        $variant = ProductVariant::factory()->for(Product::factory()->for($team))->create();
        $oldSale = $this->sale($branch, $variant, '2026-10-01 12:00:00', 1, 1200);
        $this->refund($oldSale, 1200, '2026-11-01 00:00:00');

        $this->actingAs($user)->get(route('reports.index', $team))->assertInertia(fn (Assert $page) => $page
            ->where('summary.sales_cents', 0)->where('summary.net_cents', -1200)->has('topProducts', 0));
    }

    public function test_inventory_includes_missing_stock_rows_and_filters_branch_and_zero_balances(): void
    {
        [$user, $team] = $this->member();
        $first = Branch::factory()->for($team)->create(['name' => 'A']);
        $second = Branch::factory()->for($team)->create(['name' => 'B']);
        Branch::factory()->for($team)->create(['is_active' => false]);
        $variant = ProductVariant::factory()->for(Product::factory()->for($team))->create();
        Stock::factory()->create(['team_id' => $team->id, 'branch_id' => $first->id, 'product_variant_id' => $variant->id, 'quantity' => 7]);
        ProductVariant::factory()->for(Product::factory()->for($team))->create(['is_active' => false]);
        ProductVariant::factory()->for(Product::factory()->for($team)->create(['is_active' => false]))->create();
        Stock::factory()->create(['quantity' => 999]);

        $this->actingAs($user)->get(route('reports.index', $team))->assertInertia(fn (Assert $page) => $page
            ->where('summary.units_in_stock', 7)->where('summary.out_of_stock', 1)->where('inventory.total', 2)
            ->has('stockByBranch', 2)->where('stockByBranch.0.units', fn ($units) => (int) $units === 7)
            ->where('stockByBranch.1.empty_count', fn ($count) => (int) $count === 1));
        $this->get(route('reports.index', [$team, 'branch' => $second->id, 'stock' => 'empty', 'from' => '2020-01-01', 'to' => '2020-01-02']))
            ->assertInertia(fn (Assert $page) => $page->where('inventory.total', 1)->where('inventory.data.0.quantity', 0)->where('inventory.data.0.branch_id', $second->id)->where('summary.units_in_stock', 0)
                ->has('stockByBranch', 1)->where('stockByBranch.0.branch_id', $second->id)->where('stockByBranch.0.units', fn ($units) => (int) $units === 0));
        $this->get(route('reports.index', [$team, 'branch' => $first->id, 'stock' => 'empty']))->assertInertia(fn (Assert $page) => $page->where('inventory.total', 0));
    }

    public function test_branch_filters_apply_to_sales_refunds_top_products_and_csv(): void
    {
        [$user, $team] = $this->member();
        $first = Branch::factory()->for($team)->create(['name' => 'Primera']);
        $second = Branch::factory()->for($team)->create(['name' => 'Segunda']);
        $variant = ProductVariant::factory()->for(Product::factory()->for($team))->create();
        $sale = $this->sale($first, $variant, '2026-11-10 12:00:00', 1, 2500);
        $this->refund($sale, 500, '2026-11-11 12:00:00');
        $other = $this->sale($second, $variant, '2026-11-10 12:00:00', 1, 9000);
        $this->refund($other, 1500, '2026-11-11 12:00:00');

        $this->actingAs($user)->get(route('reports.index', [$team, 'branch' => $first->id]))->assertInertia(fn (Assert $page) => $page
            ->where('summary.sales_cents', 2500)->where('summary.refunds_cents', 500)->where('topProducts.0.amount_cents', fn ($amount) => (int) $amount === 2500));
        foreach (['sales', 'returns', 'products', 'inventory'] as $type) {
            $response = $this->get(route('reports.export', [$team, 'branch' => $first->id, 'type' => $type]));
            $response->assertDownload("reporte-{$type}-2026-11-01-2026-11-15.csv");
            $this->assertStringNotContainsString('Segunda', $response->streamedContent());
        }
    }

    public function test_inventory_exports_all_pages_and_escapes_spreadsheet_formulas(): void
    {
        [$user, $team] = $this->member();
        $branch = Branch::factory()->for($team)->create(['name' => '=SUM(1,2)']);
        $product = Product::factory()->for($team)->create(['name' => '@SUM(1,2)']);
        ProductVariant::factory()->for($product)->count(25)->create();
        $variant = ProductVariant::factory()->for($product)->create(['sku' => 'LAST-CSV-ROW']);
        Stock::factory()->create(['team_id' => $team->id, 'branch_id' => $branch->id, 'product_variant_id' => $variant->id, 'quantity' => 3]);

        $this->actingAs($user)->get(route('reports.index', [$team, 'stock' => 'empty']))->assertInertia(fn (Assert $page) => $page->has('inventory.data', 24)->where('inventory.total', 25));
        $csv = $this->get(route('reports.export', [$team, 'type' => 'inventory', 'page' => 2]))->streamedContent();
        $this->assertStringContainsString('LAST-CSV-ROW', $csv);
        $this->assertStringContainsString("'=SUM(1,2)", $csv);
        $this->assertStringContainsString("'@SUM(1,2)", $csv);
        $this->assertCount(27, array_filter(explode("\n", $csv)));
        $this->assertStringNotContainsString('LAST-CSV-ROW', $this->get(route('reports.export', [$team, 'type' => 'inventory', 'stock' => 'empty']))->streamedContent());
    }

    public function test_top_products_rank_by_units_and_export_the_entire_ranking(): void
    {
        [$user, $team] = $this->member();
        $branch = Branch::factory()->for($team)->create();
        for ($units = 1; $units <= 11; $units++) {
            $variant = ProductVariant::factory()->for(Product::factory()->for($team))->create(['sku' => "RANK-{$units}"]);
            $this->sale($branch, $variant, '2026-11-10 12:00:00', $units, 100);
        }

        $this->actingAs($user)->get(route('reports.index', $team))->assertInertia(fn (Assert $page) => $page
            ->has('topProducts', 10)->where('topProducts.0.sku', 'RANK-11')->where('topProducts.9.sku', 'RANK-2'));
        $csv = $this->get(route('reports.export', [$team, 'type' => 'products']))->streamedContent();
        $this->assertCount(12, array_filter(explode("\n", $csv)));
        $this->assertStringContainsString(',RANK-1,1,1.00', $csv);
    }

    public function test_chart_intervals_cover_long_periods_without_losing_sales_or_refunds(): void
    {
        [$user, $team] = $this->member();
        $branch = Branch::factory()->for($team)->create();
        $variant = ProductVariant::factory()->for(Product::factory()->for($team))->create();
        $sale = $this->sale($branch, $variant, '2025-01-01 00:00:00', 2, 2500);
        $this->sale($branch, $variant, '2026-11-15 23:59:59', 1, 3000);
        $this->refund($sale, 1000, '2026-11-15 00:00:00');

        $this->actingAs($user)->get(route('reports.index', [$team, 'view' => 'charts', 'from' => '2025-01-01', 'to' => '2026-11-15', 'branch' => $branch->id]))
            ->assertInertia(fn (Assert $page) => $page->where('filters.view', 'charts')
                ->where('chartTrend.0.from', '2025-01-01')
                ->where('chartTrend', fn ($points) => count($points) <= 90
                    && collect($points)->sum('sales_cents') === 8000
                    && collect($points)->sum('refunds_cents') === 1000
                    && collect($points)->sum('net_cents') === 7000
                    && collect($points)->last()['to'] === '2026-11-15'));
    }

    public function test_charts_handle_a_single_empty_day_and_stock_beyond_the_first_page(): void
    {
        [$user, $team] = $this->member();
        $branch = Branch::factory()->for($team)->create();
        $product = Product::factory()->for($team)->create();
        ProductVariant::factory()->for($product)->count(25)->create();
        $last = ProductVariant::factory()->for($product)->create();
        Stock::factory()->create(['team_id' => $team->id, 'branch_id' => $branch->id, 'product_variant_id' => $last->id, 'quantity' => 42]);

        $this->actingAs($user)->get(route('reports.index', [$team, 'view' => 'charts', 'from' => '2026-11-15', 'to' => '2026-11-15']))
            ->assertInertia(fn (Assert $page) => $page->has('inventory.data', 24)->has('chartTrend', 1)
                ->where('chartTrend.0.from', '2026-11-15')->where('chartTrend.0.to', '2026-11-15')
                ->where('chartTrend.0.sales_cents', 0)->where('chartTrend.0.refunds_cents', 0)
                ->where('stockByBranch.0.units', fn ($units) => (int) $units === 42)
                ->where('stockByBranch.0.variants', fn ($count) => (int) $count === 26));
    }

    public function test_filters_validate_dates_types_and_foreign_branches_before_exporting(): void
    {
        [$user, $team] = $this->member();
        $branch = Branch::factory()->create();

        $this->actingAs($user)->get(route('reports.index', [$team, 'branch' => $branch->id]))->assertSessionHasErrors('branch');
        $this->get(route('reports.export', [$team, 'from' => '2026-11-10', 'to' => '2026-11-01']))->assertSessionHasErrors('to');
        $this->get(route('reports.index', [$team, 'from' => 'invalid']))->assertSessionHasErrors('from');
        $this->get(route('reports.export', [$team, 'type' => 'unknown']))->assertSessionHasErrors('type');
        $this->get(route('reports.index', [$team, 'view' => 'unknown']))->assertSessionHasErrors('view');
    }

    /** @return array{User, Team} */
    private function member(TeamRole $role = TeamRole::Owner): array
    {
        $team = Team::factory()->create();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->members()->attach($user, ['role' => $role->value]);

        return [$user, $team];
    }

    private function sale(Branch $branch, ProductVariant $variant, string $completedAt, int $quantity, int $unitPrice): Order
    {
        $order = Order::factory()->for($branch)->create(['status' => OrderStatus::Confirmed, 'completed_at' => $completedAt, 'total_cents' => $quantity * $unitPrice]);
        $order->items()->create(['product_variant_id' => $variant->id, 'product_name' => $variant->product->name, 'variant_name' => $variant->name, 'sku' => $variant->sku, 'quantity' => $quantity, 'unit_price_cents' => $unitPrice]);

        return $order;
    }

    private function refund(Order $order, int $amount, string $date): void
    {
        OrderReturn::factory()->create(['order_id' => $order->id, 'order_item_id' => $order->items()->sole()->id, 'amount_cents' => $amount, 'created_at' => $date]);
    }
}
