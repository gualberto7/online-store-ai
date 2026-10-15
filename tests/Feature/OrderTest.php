<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\TeamRole;
use App\Models\Branch;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Stock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_must_sign_in(): void
    {
        $order = Order::factory()->create();

        $this->get(route('orders.index', $order->team))->assertRedirect(route('login'));
    }

    public function test_drafts_snapshot_catalog_prices_and_ignore_untrusted_totals(): void
    {
        [$user, $branch, $variant] = $this->catalog();

        $response = $this->actingAs($user)->post(route('orders.store', $branch->team), [
            'branch_id' => $branch->id, 'customer_name' => 'Ana',
            'items' => [['variant_id' => (string) $variant->id, 'quantity' => '3']],
            'total_cents' => 1, 'status' => 'confirmed', 'user_id' => 999,
        ]);

        $order = $branch->team->orders()->sole();
        $response->assertRedirect(route('orders.show', [$branch->team, $order]));
        $this->assertSame(OrderStatus::Draft, $order->status);
        $this->assertSame(3003, $order->total_cents);
        $this->assertSame($user->id, $order->user_id);
        $this->assertDatabaseHas('order_items', ['order_id' => $order->id, 'sku' => 'SKU-1', 'unit_price_cents' => 1001, 'quantity' => 3]);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseCount('stocks', 0);
    }

    public function test_confirmation_uses_saved_prices_and_deducts_stock_once(): void
    {
        [$user, $branch, $variant] = $this->catalog();
        $order = $this->draft($branch, $variant, 3);
        $stock = $this->stock($branch, $variant, 10);
        $variant->update(['price' => '99.99', 'sku' => 'NEW-SKU', 'name' => 'Nuevo nombre']);
        $url = route('orders.confirm', [$branch->team, $order]);

        $this->actingAs($user)->post($url)->assertRedirect(route('orders.show', [$branch->team, $order]));
        $this->post($url)->assertRedirect();

        $this->assertSame(7, $stock->fresh()->quantity);
        $this->assertSame(OrderStatus::Confirmed, $order->fresh()->status);
        $this->assertNotNull($order->fresh()->confirmed_at);
        $this->assertSame(3003, $order->fresh()->total_cents);
        $this->assertDatabaseHas('order_items', ['order_id' => $order->id, 'sku' => 'SKU-1', 'unit_price_cents' => 1001]);
        $this->assertDatabaseHas('stock_movements', ['order_id' => $order->id, 'user_id' => $user->id, 'type' => 'issue', 'quantity_change' => -3, 'balance_before' => 10, 'balance_after' => 7]);
        $this->assertDatabaseCount('stock_movements', 1);
    }

    public function test_insufficient_stock_rolls_back_all_lines(): void
    {
        [$user, $branch, $variant] = $this->catalog();
        $order = $this->draft($branch, $variant, 3);
        $stock = $this->stock($branch, $variant, 10);
        $other = ProductVariant::factory()->for($variant->product)->create();
        $order->items()->create(['product_variant_id' => $other->id, 'product_name' => 'Producto', 'variant_name' => 'Estándar', 'sku' => $other->sku, 'quantity' => 2, 'unit_price_cents' => 100]);

        $this->actingAs($user)->post(route('orders.confirm', [$branch->team, $order]))->assertSessionHasErrors('quantity');

        $this->assertSame(10, $stock->fresh()->quantity);
        $this->assertSame(OrderStatus::Draft, $order->fresh()->status);
        $this->assertNull($order->fresh()->confirmed_at);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseCount('stocks', 1);
    }

    public function test_two_orders_cannot_sell_more_than_the_available_stock(): void
    {
        [$user, $branch, $variant] = $this->catalog();
        $first = $this->draft($branch, $variant, 3);
        $second = $this->draft($branch, $variant, 3);
        $stock = $this->stock($branch, $variant, 5);

        $this->actingAs($user)->post(route('orders.confirm', [$branch->team, $first]))->assertRedirect();
        $this->post(route('orders.confirm', [$branch->team, $second]))->assertSessionHasErrors('quantity');

        $this->assertSame(2, $stock->fresh()->quantity);
        $this->assertSame(OrderStatus::Draft, $second->fresh()->status);
        $this->assertDatabaseCount('stock_movements', 1);
    }

    public function test_cancelling_a_confirmed_order_restores_stock_only_once_even_if_inactive(): void
    {
        [$user, $branch, $variant] = $this->catalog();
        $order = $this->draft($branch, $variant, 3);
        $stock = $this->stock($branch, $variant, 10);
        $this->actingAs($user)->post(route('orders.confirm', [$branch->team, $order]))->assertRedirect();
        $branch->update(['is_active' => false]);
        $variant->update(['is_active' => false]);

        $this->post(route('orders.cancel', [$branch->team, $order]), ['reason' => 'Cliente canceló'])->assertRedirect();
        $this->post(route('orders.cancel', [$branch->team, $order]), ['reason' => 'Reintento'])->assertRedirect();

        $this->assertSame(10, $stock->fresh()->quantity);
        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        $this->assertSame('Cliente canceló', $order->fresh()->cancellation_reason);
        $this->assertDatabaseHas('stock_movements', ['order_id' => $order->id, 'type' => 'receipt', 'quantity_change' => 3]);
        $this->assertDatabaseCount('stock_movements', 2);
    }

    public function test_cancelled_drafts_do_not_change_inventory_and_cannot_be_confirmed(): void
    {
        [$user, $branch, $variant] = $this->catalog();
        $order = $this->draft($branch, $variant, 3);

        $this->actingAs($user)->post(route('orders.cancel', [$branch->team, $order]), ['reason' => 'Error'])->assertRedirect();
        $this->post(route('orders.confirm', [$branch->team, $order]))->assertSessionHasErrors('order');

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        $this->assertDatabaseCount('stocks', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_cancellation_requires_a_reason(): void
    {
        [$user, $branch, $variant] = $this->catalog();
        $order = $this->draft($branch, $variant, 1);

        $this->actingAs($user)->post(route('orders.cancel', [$branch->team, $order]), [])->assertSessionHasErrors('reason');

        $this->assertSame(OrderStatus::Draft, $order->fresh()->status);
    }

    #[TestWith(['owner', true])]
    #[TestWith(['admin', true])]
    #[TestWith(['member', false])]
    public function test_order_permissions_follow_business_roles(string $role, bool $allowed): void
    {
        [$user, $branch, $variant] = $this->catalog(TeamRole::from($role));
        $order = $this->draft($branch, $variant, 1);
        $other = Order::factory()->create();

        $this->assertTrue($user->can('view', $order));
        $this->assertSame($allowed, $user->can('create', [Order::class, $branch->team]));
        $this->assertSame($allowed, $user->can('manage', $order));
        $this->assertFalse($user->can('manage', $other));
        $this->assertFalse($user->can('view', $other));
    }

    public function test_members_can_read_orders_but_cannot_create_confirm_or_cancel(): void
    {
        [$user, $branch, $variant] = $this->catalog(TeamRole::Member);
        $order = $this->draft($branch, $variant, 1);

        $this->actingAs($user)->get(route('orders.show', [$branch->team, $order]))
            ->assertInertia(fn (Assert $page) => $page->component('orders/show')->where('canManage', false));
        $this->get(route('orders.create', $branch->team))->assertForbidden();
        $this->post(route('orders.store', $branch->team), [])->assertForbidden();
        $this->post(route('orders.confirm', [$branch->team, $order]))->assertForbidden();
        $this->post(route('orders.cancel', [$branch->team, $order]), ['reason' => 'Error'])->assertForbidden();

        $this->assertSame(OrderStatus::Draft, $order->fresh()->status);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_cross_business_orders_are_not_found(): void
    {
        [$user, $branch] = $this->catalog();
        $other = Order::factory()->create();

        $this->actingAs($user)->get(route('orders.show', [$branch->team, $other]))->assertNotFound();
        $this->post(route('orders.confirm', [$branch->team, $other]))->assertNotFound();
        $this->post(route('orders.cancel', [$branch->team, $other]), ['reason' => 'Error'])->assertNotFound();

        $this->assertSame(OrderStatus::Draft, $other->fresh()->status);
    }

    public function test_orders_index_and_creation_form_are_scoped_to_the_business(): void
    {
        [$user, $branch, $variant] = $this->catalog();
        $this->draft($branch, $variant, 1);
        Order::factory()->create();
        ProductVariant::factory()->create();

        $this->actingAs($user)->get(route('orders.index', $branch->team))
            ->assertInertia(fn (Assert $page) => $page->component('orders/index')->has('orders.data', 1));
        $this->get(route('orders.create', $branch->team))
            ->assertInertia(fn (Assert $page) => $page->component('orders/create')->has('branches', 1)->has('variants', 1));
    }

    public function test_orders_cannot_use_another_business_branch_or_variant(): void
    {
        [$user, $branch, $variant] = $this->catalog();
        $otherBranch = Branch::factory()->create();
        $otherVariant = ProductVariant::factory()->create();

        $this->actingAs($user)->post(route('orders.store', $branch->team), [
            'branch_id' => $otherBranch->id, 'items' => [['variant_id' => $otherVariant->id, 'quantity' => 1]],
        ])->assertSessionHasErrors(['branch_id', 'items.0.variant_id']);

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
    }

    public function test_duplicate_variants_and_invalid_quantities_are_rejected(): void
    {
        [$user, $branch, $variant] = $this->catalog();

        $this->actingAs($user)->post(route('orders.store', $branch->team), [
            'branch_id' => $branch->id, 'items' => [
                ['variant_id' => $variant->id, 'quantity' => 0],
                ['variant_id' => $variant->id, 'quantity' => '1.5'],
            ],
        ])->assertSessionHasErrors(['items.0.variant_id', 'items.0.quantity', 'items.1.quantity']);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_inactive_products_are_rejected_when_creating_orders(): void
    {
        [$user, $branch, $variant] = $this->catalog();
        $variant->product->update(['is_active' => false]);

        $this->actingAs($user)->post(route('orders.store', $branch->team), [
            'branch_id' => $branch->id, 'items' => [['variant_id' => $variant->id, 'quantity' => 1]],
        ])->assertSessionHasErrors('items.0.variant_id');

        $this->assertDatabaseCount('orders', 0);
    }

    #[TestWith(['branch'])]
    #[TestWith(['variant'])]
    #[TestWith(['product'])]
    public function test_inactive_catalog_or_branch_cannot_be_confirmed(string $inactive): void
    {
        [$user, $branch, $variant] = $this->catalog();
        $order = $this->draft($branch, $variant, 1);
        match ($inactive) {
            'branch' => $branch->update(['is_active' => false]),
            'variant' => $variant->update(['is_active' => false]),
            'product' => $variant->product->update(['is_active' => false]),
        };

        $this->actingAs($user)->post(route('orders.confirm', [$branch->team, $order]))->assertSessionHasErrors('order');

        $this->assertSame(OrderStatus::Draft, $order->fresh()->status);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_creating_orders_requires_a_branch_and_at_least_one_item(): void
    {
        [$user, $branch] = $this->catalog();

        $this->actingAs($user)->post(route('orders.store', $branch->team), [])
            ->assertSessionHasErrors(['branch_id', 'items']);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_cancellation_failure_does_not_partially_restore_stock(): void
    {
        [$user, $branch, $variant] = $this->catalog();
        $order = $this->draft($branch, $variant, 1);
        $firstStock = $this->stock($branch, $variant, 10);
        $other = ProductVariant::factory()->for($variant->product)->create();
        $secondStock = $this->stock($branch, $other, 10);
        $order->items()->create(['product_variant_id' => $other->id, 'product_name' => 'Producto', 'variant_name' => 'Estándar', 'sku' => $other->sku, 'quantity' => 1, 'unit_price_cents' => 100]);
        $this->actingAs($user)->post(route('orders.confirm', [$branch->team, $order]))->assertRedirect();
        $secondStock->update(['quantity' => 2147483647]);

        $this->post(route('orders.cancel', [$branch->team, $order]), ['reason' => 'Error'])->assertSessionHasErrors('quantity');

        $this->assertSame(9, $firstStock->fresh()->quantity);
        $this->assertSame(OrderStatus::Confirmed, $order->fresh()->status);
        $this->assertNull($order->fresh()->cancelled_at);
        $this->assertDatabaseCount('stock_movements', 2);
    }

    public function test_excessive_totals_are_rejected_without_saving_partial_orders(): void
    {
        [$user, $branch, $variant] = $this->catalog();
        $variant->update(['price' => '9999999999.99']);

        $this->actingAs($user)->post(route('orders.store', $branch->team), [
            'branch_id' => $branch->id, 'items' => [['variant_id' => $variant->id, 'quantity' => 1000]],
        ])->assertSessionHasErrors('items');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
    }

    public function test_empty_drafts_cannot_be_confirmed(): void
    {
        [$user, $branch] = $this->catalog();
        $order = Order::factory()->create(['branch_id' => $branch->id, 'team_id' => $branch->team_id]);

        $this->actingAs($user)->post(route('orders.confirm', [$branch->team, $order]))->assertSessionHasErrors('order');

        $this->assertSame(OrderStatus::Draft, $order->fresh()->status);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    /** @return array{User, Branch, ProductVariant} */
    private function catalog(TeamRole $role = TeamRole::Admin): array
    {
        $user = User::factory()->create();
        $branch = Branch::factory()->create();
        $branch->team->members()->attach($user, ['role' => $role->value]);
        $product = Product::factory()->for($branch->team)->create();
        $variant = ProductVariant::factory()->for($product)->create(['sku' => 'SKU-1', 'price' => '10.01']);

        return [$user, $branch, $variant];
    }

    private function draft(Branch $branch, ProductVariant $variant, int $quantity): Order
    {
        $order = Order::factory()->create(['branch_id' => $branch->id, 'team_id' => $branch->team_id, 'total_cents' => 1001 * $quantity]);
        $order->items()->create(['product_variant_id' => $variant->id, 'product_name' => $variant->product->name, 'variant_name' => $variant->name, 'sku' => $variant->sku, 'quantity' => $quantity, 'unit_price_cents' => 1001]);

        return $order;
    }

    private function stock(Branch $branch, ProductVariant $variant, int $quantity): Stock
    {
        return Stock::factory()->create(['branch_id' => $branch->id, 'team_id' => $branch->team_id, 'product_variant_id' => $variant->id, 'quantity' => $quantity]);
    }
}
