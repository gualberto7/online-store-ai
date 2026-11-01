<?php

namespace Tests\Feature;

use App\Actions\BuildStoreReports;
use App\Actions\CompleteOrder;
use App\Actions\RecordStockMovement;
use App\Enums\TeamRole;
use App\Models\Branch;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\ReportDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class ReportDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_operations_populate_six_months_and_keep_inventory_balances_consistent(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-01 20:00:00'));
        [$team] = $this->catalog();
        Http::preventStrayRequests();

        $this->seed(ReportDemoSeeder::class);

        $this->assertGreaterThan(300, $team->orders()->count());
        $this->assertSame(2, $team->branches()->count());
        $this->assertGreaterThan(10, OrderReturn::query()->count());
        $this->assertSame(0, $team->orders()->where('created_at', '<', '2026-05-01')->count());
        $this->assertSame(0, $team->orders()->where('completed_at', '>', now())->count());
        $this->assertSame(0, OrderReturn::query()->where('created_at', '>', now())->count());
        $this->assertSame(3, $team->orders()->whereNotNull('completed_at')->distinct()->count('payment_method'));
        $reports = app(BuildStoreReports::class);
        foreach (['05', '06', '07', '08', '09', '10'] as $month) {
            $summary = $reports->summary($team, "2026-{$month}-01", CarbonImmutable::parse("2026-{$month}-01")->endOfMonth()->toDateString());
            $this->assertGreaterThan(0, $summary['sales_cents']);
            $this->assertGreaterThan(0, $summary['refunds_cents']);
        }
        $this->assertGreaterThan(0, $reports->summary($team, '2026-11-01', '2026-11-01')['sales_cents']);
        $this->assertGreaterThan(0, Stock::query()->where('quantity', 0)->count());
        foreach (Stock::query()->with(['movements' => fn ($query) => $query->orderBy('created_at')->orderBy('id')])->get() as $stock) {
            $balance = 0;
            foreach ($stock->movements as $movement) {
                $this->assertSame($balance, $movement->balance_before);
                $balance += $movement->quantity_change;
                $this->assertSame($balance, $movement->balance_after);
                $this->assertGreaterThanOrEqual(0, $balance);
            }
            $this->assertSame($balance, $stock->quantity);
        }
        $this->assertSame('2026-11-01 20:00:00', now()->toDateTimeString());
    }

    public function test_rerunning_the_seeder_preserves_orders_stock_and_existing_business_data(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-01 20:00:00'));
        [$team, $user, $variant] = $this->catalog();
        $realBranch = Branch::factory()->for($team)->create(['name' => 'Sucursal actual']);
        app(RecordStockMovement::class)->handle($realBranch, $variant, $user, 'receipt', 7, 'Entrada original');
        $realOrder = Order::factory()->for($realBranch)->create(['customer_name' => 'Cliente existente']);
        $foreign = Stock::factory()->create(['quantity' => 19]);
        $this->seed(ReportDemoSeeder::class);
        $demo = $team->orders()->where('notes', 'like', '[DEMO-REPORTS:v1]%')->firstOrFail();
        $demo->update(['notes' => 'Nota editada por el usuario']);
        $counts = [Order::count(), OrderReturn::count(), StockMovement::count(), Branch::count()];
        $balances = Stock::query()->orderBy('id')->pluck('quantity', 'id')->all();

        $this->seed(ReportDemoSeeder::class);

        $this->assertSame($counts, [Order::count(), OrderReturn::count(), StockMovement::count(), Branch::count()]);
        $this->assertSame($balances, Stock::query()->orderBy('id')->pluck('quantity', 'id')->all());
        $this->assertDatabaseHas('stocks', ['branch_id' => $realBranch->id, 'quantity' => 7]);
        $this->assertDatabaseHas('stocks', ['id' => $foreign->id, 'quantity' => 19]);
        $this->assertDatabaseHas('orders', ['id' => $realOrder->id, 'customer_name' => 'Cliente existente']);
        $this->assertDatabaseHas('orders', ['id' => $demo->id, 'notes' => 'Nota editada por el usuario']);
        $this->assertSame('2026-11-01 20:00:00', now()->toDateTimeString());
    }

    public function test_failed_generation_rolls_back_demo_data_and_restores_the_clock(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-01 20:00:00'));
        $this->catalog();
        $this->mock(CompleteOrder::class, function ($mock): void {
            $mock->shouldReceive('handle')->once()->andThrow(new RuntimeException('Fallo de prueba'));
        });

        try {
            $this->seed(ReportDemoSeeder::class);
            $this->fail('La generación debía fallar.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Fallo de prueba', $exception->getMessage());
        }

        $this->assertDatabaseCount('branches', 0);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('stocks', 0);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertSame('2026-11-01 20:00:00', now()->toDateTimeString());
    }

    /** @return array{Team, User, ProductVariant} */
    private function catalog(): array
    {
        config(['semantic-search.enabled' => false]);
        $team = Team::factory()->create(['slug' => 'tienda-demo']);
        $user = User::factory()->create(['email' => 'admin@test.com', 'current_team_id' => $team->id]);
        $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
        $product = Product::factory()->for($team)->create();
        $variants = ProductVariant::factory()->for($product)->count(4)->create();

        return [$team, $user, $variants->firstOrFail()];
    }
}
