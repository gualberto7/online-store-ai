<?php

namespace Tests\Feature;

use App\Ai\Agents\ReportAssistant;
use App\Ai\Tools\QueryStoreReports;
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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Exceptions;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Tools\Request;
use PHPUnit\Framework\Attributes\TestWith;
use RuntimeException;
use Tests\TestCase;

class ReportAssistantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['reports-ai.enabled' => true, 'semantic-search.enabled' => false]);
    }

    public function test_agent_executes_a_real_tool_and_returns_server_calculated_rows(): void
    {
        [$user, $team, $branch, $variant] = $this->catalog();
        $this->sale($branch, $variant, 2, 1250);
        ReportAssistant::fake([new ToolCall('call-report', 'QueryStoreReports', ['report' => 'top_products', 'sort' => 'units']), 'Estos son los productos más vendidos.'])->preventStrayPrompts();

        $this->actingAs($user)->postJson(route('reports.ai', $team), ['prompt' => 'Productos más vendidos', 'from' => '2026-11-01', 'to' => '2026-11-30'])
            ->assertOk()->assertJsonPath('answer', 'Estos son los productos más vendidos.')
            ->assertJsonPath('reports.0.rows.0.units', 2)->assertJsonPath('reports.0.rows.0.amount_cents', 2500)
            ->assertJsonPath('reports.0.from', '2026-11-01');
    }

    #[TestWith(['member'])]
    #[TestWith(['foreign'])]
    #[TestWith(['guest'])]
    public function test_unauthorized_requests_never_prompt_the_agent(string $type): void
    {
        [$user, $team] = $this->catalog();
        ReportAssistant::fake()->preventStrayPrompts();
        if ($type === 'member') {
            $team->members()->updateExistingPivot($user, ['role' => TeamRole::Member->value]);
        } elseif ($type === 'foreign') {
            $team = Team::factory()->create();
        }
        if ($type !== 'guest') {
            $this->actingAs($user);
        }

        $response = $this->postJson(route('reports.ai', $team), ['prompt' => 'Ventas']);

        $type === 'guest' ? $response->assertUnauthorized() : $response->assertForbidden();
        ReportAssistant::assertNeverPrompted();
    }

    public function test_request_validation_and_foreign_branches_do_not_reach_the_agent(): void
    {
        [$user, $team] = $this->catalog();
        $foreign = Branch::factory()->create();
        ReportAssistant::fake()->preventStrayPrompts();

        $this->actingAs($user)->postJson(route('reports.ai', $team), [])->assertUnprocessable()->assertJsonValidationErrors('prompt');
        $this->postJson(route('reports.ai', $team), ['prompt' => str_repeat('x', 2001)])->assertJsonValidationErrors('prompt');
        $this->postJson(route('reports.ai', $team), ['prompt' => 'Ventas', 'branch' => $foreign->id])->assertJsonValidationErrors('branch');
        ReportAssistant::assertNeverPrompted();
    }

    public function test_provider_failure_returns_a_safe_error_and_releases_the_lock(): void
    {
        [$user, $team] = $this->catalog();
        ReportAssistant::fake(fn () => throw new RuntimeException('provider-private-detail'))->preventStrayPrompts();
        Exceptions::fake();

        $this->actingAs($user)->postJson(route('reports.ai', $team), ['prompt' => 'Ventas'])->assertServiceUnavailable()
            ->assertJsonMissing(['message' => 'provider-private-detail']);
        $lock = Cache::lock('report-ai:'.$team->id.':'.$user->id, 180);
        $this->assertTrue($lock->get());
        $lock->release();
        Exceptions::assertReported(RuntimeException::class);
    }

    public function test_disabled_provider_or_concurrent_request_does_not_call_the_agent(): void
    {
        [$user, $team] = $this->catalog();
        ReportAssistant::fake()->preventStrayPrompts();
        config(['reports-ai.enabled' => false]);
        $this->actingAs($user)->postJson(route('reports.ai', $team), ['prompt' => 'Ventas'])->assertServiceUnavailable();
        config(['reports-ai.enabled' => true]);
        $lock = Cache::lock('report-ai:'.$team->id.':'.$user->id, 180);
        $lock->get();

        $this->postJson(route('reports.ai', $team), ['prompt' => 'Ventas'])->assertConflict();

        $lock->release();
        ReportAssistant::assertNeverPrompted();
    }

    public function test_rate_limit_stops_additional_provider_calls(): void
    {
        [$user, $team] = $this->catalog();
        ReportAssistant::fake(['Aclara el período.', 'Aclara el período.', 'Aclara el período.', 'Aclara el período.', 'Aclara el período.'])->preventStrayPrompts();
        $this->actingAs($user);
        for ($index = 0; $index < 5; $index++) {
            $this->postJson(route('reports.ai', $team), ['prompt' => 'Ventas'])->assertOk();
        }

        $this->postJson(route('reports.ai', $team), ['prompt' => 'Ventas'])->assertTooManyRequests();

        ReportAssistant::assertPromptedTimes(5);
    }

    public function test_tool_rejects_oversized_date_ranges_and_reversed_dates(): void
    {
        [$user, $team] = $this->catalog();
        $tool = new QueryStoreReports($team, $user, '2026-11-01', '2026-11-30');

        $this->assertArrayHasKey('error', $this->runTool($tool, ['report' => 'sales_summary', 'from' => '2020-01-01', 'to' => '2026-11-01']));
        $this->assertArrayHasKey('error', $this->runTool($tool, ['report' => 'sales_summary', 'from' => '2026-11-30', 'to' => '2026-11-01']));
        $this->assertSame([], $tool->reports);
    }

    public function test_customers_are_grouped_by_contact_without_merging_names_or_exposing_contacts(): void
    {
        [$user, $team, $branch, $variant] = $this->catalog();
        $this->sale($branch, $variant, 1, 1000, ['customer_name' => 'Ana', 'customer_email' => 'ANA@example.test']);
        $this->sale($branch, $variant, 2, 1000, ['customer_name' => 'Ana', 'customer_email' => ' ana@example.test ']);
        $this->sale($branch, $variant, 1, 2000, ['customer_name' => 'Ana', 'customer_email' => 'otra@example.test']);
        $this->sale($branch, $variant, 1, 9000, ['customer_name' => 'Ana']);
        $this->sale($branch, $variant, 1, 1500, ['customer_name' => 'Luis', 'customer_phone' => '+591 (700) 123-45']);
        $this->sale($branch, $variant, 1, 1500, ['customer_name' => 'Luis', 'customer_phone' => '59170012345']);

        $tool = new QueryStoreReports($team, $user, '2026-11-01', '2026-11-30');
        $result = $this->runTool($tool, ['report' => 'top_customers']);

        $this->assertCount(3, $result['rows']);
        $this->assertSame(3000, $result['rows'][0]['amount_cents']);
        $this->assertSame(2, $result['rows'][0]['orders']);
        $this->assertStringContainsString('falta de identificador: 1', $result['notes']);
        $this->assertStringNotContainsString('ana@example.test', json_encode($result));
        $this->assertStringNotContainsString('59170012345', json_encode($result));
    }

    public function test_each_tool_report_respects_tenant_dates_and_branch_and_uses_historical_prices(): void
    {
        [$user, $team, $branch, $variant] = $this->catalog();
        $sale = $this->sale($branch, $variant, 2, 1250, ['customer_email' => 'cliente@example.test']);
        $otherBranch = Branch::factory()->for($team)->create();
        $this->sale($otherBranch, $variant, 9, 4000);
        $foreign = Branch::factory()->create();
        $foreignVariant = ProductVariant::factory()->for(Product::factory()->create(['team_id' => $foreign->team_id]))->create();
        $this->sale($foreign, $foreignVariant, 9, 4000);
        $this->sale($branch, $variant, 1, 5000, ['completed_at' => '2026-10-31 23:59:59']);
        OrderReturn::factory()->create(['order_id' => $sale->id, 'order_item_id' => $sale->items()->sole()->id, 'quantity' => 1, 'amount_cents' => 1250, 'created_at' => '2026-11-12 12:00:00']);
        Stock::factory()->create(['team_id' => $team->id, 'branch_id' => $branch->id, 'product_variant_id' => $variant->id, 'quantity' => 7]);
        $expected = ['sales_summary' => ['sales_cents', 2500], 'top_products' => ['amount_cents', 2500], 'top_customers' => ['amount_cents', 2500], 'sales_by_branch' => ['amount_cents', 2500], 'returns' => ['amount_cents', 1250], 'inventory' => ['quantity', 7]];
        foreach ($expected as $report => [$key, $value]) {
            $tool = new QueryStoreReports($team, $user, '2026-11-01', '2026-11-30', $branch->id);
            $result = $this->runTool($tool, ['report' => $report]);
            $this->assertCount(1, $result['rows']);
            $this->assertSame($value, $result['rows'][0][$key], $report);
        }
        $result = $this->runTool(new QueryStoreReports($team, $user, '2026-11-01', '2026-11-30', $branch->id), ['report' => 'sales_timeline']);
        $this->assertSame(2500, array_sum(array_column($result['rows'], 'sales_cents')));
        $this->assertSame(1250, array_sum(array_column($result['rows'], 'refunds_cents')));
    }

    #[TestWith(['branch_id', -1])]
    #[TestWith(['report', 'execute_sql'])]
    #[TestWith(['sort', 'amount_cents; DROP TABLE orders'])]
    #[TestWith(['limit', 5000])]
    #[TestWith(['sql', 'SELECT * FROM users'])]
    #[TestWith(['team_id', 999])]
    public function test_invalid_tool_arguments_cannot_select_arbitrary_data(string $key, mixed $value): void
    {
        [$user, $team] = $this->catalog();
        $tool = new QueryStoreReports($team, $user, '2026-11-01', '2026-11-30');

        $result = $this->runTool($tool, ['report' => 'sales_summary', $key => $value]);

        $this->assertArrayHasKey('error', $result);
        $this->assertSame([], $tool->reports);
    }

    public function test_tool_rejects_foreign_branches_even_if_agent_requests_them(): void
    {
        [$user, $team] = $this->catalog();
        $foreign = Branch::factory()->create();
        $tool = new QueryStoreReports($team, $user, '2026-11-01', '2026-11-30');

        $this->assertArrayHasKey('error', $this->runTool($tool, ['report' => 'inventory', 'branch_id' => $foreign->id]));
        $this->assertSame([], $tool->reports);
    }

    public function test_tool_sort_search_empty_inventory_and_report_limits_are_enforced(): void
    {
        [$user, $team, $branch, $variant] = $this->catalog();
        $this->sale($branch, $variant, 2, 2000);
        $other = ProductVariant::factory()->for($variant->product)->create(['sku' => 'OTHER-SKU']);
        $this->sale($branch, $other, 1, 1000);
        $tool = new QueryStoreReports($team, $user, '2026-11-01', '2026-11-30');

        $result = $this->runTool($tool, ['report' => 'top_products', 'sort' => 'units', 'direction' => 'asc', 'limit' => 1]);
        $this->assertCount(1, $result['rows']);
        $this->assertSame(1, $result['rows'][0]['units']);
        $result = $this->runTool($tool, ['report' => 'inventory', 'search' => 'OTHER-SKU', 'only_empty' => true]);
        $this->assertCount(1, $result['rows']);
        $this->assertSame(0, $result['rows'][0]['quantity']);
        Stock::factory()->create(['team_id' => $team->id, 'branch_id' => $branch->id, 'product_variant_id' => $other->id, 'quantity' => 5]);
        $filteredTool = new QueryStoreReports($team, $user, '2026-11-01', '2026-11-30', $branch->id, true);
        $filteredResult = $this->runTool($filteredTool, ['report' => 'inventory']);
        $this->assertCount(1, $filteredResult['rows']);
        $this->assertSame($variant->sku, $filteredResult['rows'][0]['sku']);
        $this->runTool($tool, ['report' => 'sales_summary']);
        $this->runTool($tool, ['report' => 'returns']);
        $this->assertArrayHasKey('error', $this->runTool($tool, ['report' => 'sales_summary']));
    }

    /** @return array<string, mixed> */
    private function runTool(QueryStoreReports $tool, array $arguments): array
    {
        return json_decode($tool->handle(new Request($arguments)), true, flags: JSON_THROW_ON_ERROR);
    }

    /** @return array{User, Team, Branch, ProductVariant} */
    private function catalog(): array
    {
        $team = Team::factory()->create();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
        $branch = Branch::factory()->for($team)->create();
        $variant = ProductVariant::factory()->for(Product::factory()->for($team))->create(['price' => '999.99']);

        return [$user, $team, $branch, $variant];
    }

    private function sale(Branch $branch, ProductVariant $variant, int $quantity, int $price, array $attributes = []): Order
    {
        $order = Order::factory()->for($branch)->create([...['status' => OrderStatus::Confirmed, 'completed_at' => '2026-11-10 12:00:00', 'total_cents' => $quantity * $price], ...$attributes]);
        $order->items()->create(['product_variant_id' => $variant->id, 'product_name' => $variant->product->name, 'variant_name' => $variant->name, 'sku' => $variant->sku, 'quantity' => $quantity, 'unit_price_cents' => $price]);

        return $order;
    }
}
