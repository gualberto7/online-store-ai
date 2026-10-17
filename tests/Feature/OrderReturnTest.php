<?php

namespace Tests\Feature;

use App\Actions\CompleteOrder;
use App\Actions\ConfirmOrder;
use App\Actions\CreateOrder;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\TeamRole;
use App\Models\Branch;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Stock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class OrderReturnTest extends TestCase
{
    use RefreshDatabase;

    public function test_partial_return_uses_original_price_and_restocks_only_the_selected_branch(): void
    {
        [$user, $order, $stock] = $this->completedOrder();
        $item = $order->items()->sole();
        $item->variant->update(['price' => '99.99']);
        $otherBranch = Branch::factory()->for($order->team)->create();
        $otherStock = Stock::factory()->create(['team_id' => $order->team_id, 'branch_id' => $otherBranch->id, 'product_variant_id' => $item->product_variant_id, 'quantity' => 7]);

        $this->actingAs($user)->post(route('orders.returns.store', [$order->team, $order]), [...$this->data($order), 'restock' => true, 'amount_cents' => 1, 'user_id' => 999])
            ->assertRedirectToRoute('orders.show', ['current_team' => $order->team, 'order' => $order]);

        $return = $order->returns()->sole();
        $this->assertSame(1099, $return->amount_cents);
        $this->assertSame($user->id, $return->user_id);
        $this->assertDatabaseHas('stocks', ['id' => $stock->id, 'quantity' => 3]);
        $this->assertDatabaseHas('stocks', ['id' => $otherStock->id, 'quantity' => 7]);
        $this->assertDatabaseHas('stock_movements', ['id' => $return->stock_movement_id, 'order_id' => $order->id, 'quantity_change' => 1, 'user_id' => $user->id]);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'total_cents' => 3297, 'status' => 'confirmed']);
        $this->get(route('orders.show', [$order->team, $order]))->assertInertia(fn (Assert $page) => $page->has('order.returns', 1)
            ->where('order.returns.0.amount_cents', 1099)->where('order.returns.0.user.name', $user->name));
    }

    public function test_damaged_items_can_be_refunded_without_increasing_stock(): void
    {
        [$user, $order, $stock] = $this->completedOrder();

        $this->actingAs($user)->post(route('orders.returns.store', [$order->team, $order]), [...$this->data($order), 'quantity' => 2, 'restock' => false])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('order_returns', ['order_id' => $order->id, 'quantity' => 2, 'amount_cents' => 2198, 'restock' => false, 'stock_movement_id' => null]);
        $this->assertDatabaseHas('stocks', ['id' => $stock->id, 'quantity' => 2]);
        $this->assertDatabaseCount('stock_movements', 1);
    }

    public function test_retries_do_not_duplicate_refunds_or_stock_movements(): void
    {
        [$user, $order, $stock] = $this->completedOrder();
        $data = [...$this->data($order), 'restock' => true];
        $url = route('orders.returns.store', [$order->team, $order]);
        $this->actingAs($user)->post($url, $data)->assertSessionHasNoErrors();

        $this->post($url, [...$data, 'quantity' => 3, 'reason' => 'Changed'])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('order_returns', 1);
        $this->assertDatabaseHas('order_returns', ['quantity' => 1, 'reason' => 'Cambio de opinión', 'amount_cents' => 1099]);
        $this->assertDatabaseHas('stocks', ['id' => $stock->id, 'quantity' => 3]);
        $this->assertDatabaseCount('stock_movements', 2);
    }

    public function test_multiple_partial_returns_cannot_exceed_sold_units(): void
    {
        [$user, $order, $stock] = $this->completedOrder();
        $url = route('orders.returns.store', [$order->team, $order]);
        $this->actingAs($user)->post($url, [...$this->data($order), 'quantity' => 2, 'restock' => true])->assertSessionHasNoErrors();

        $this->post($url, [...$this->data($order), 'quantity' => 2, 'restock' => true])
            ->assertSessionHasErrors(['quantity' => 'La cantidad supera las unidades pendientes de devolución.']);
        $this->post($url, [...$this->data($order), 'quantity' => 1, 'restock' => true])->assertSessionHasNoErrors();
        $this->post($url, $this->data($order))->assertSessionHasErrors('quantity');

        $this->assertDatabaseCount('order_returns', 2);
        $this->assertSame(3297, (int) $order->returns()->sum('amount_cents'));
        $this->assertDatabaseHas('stocks', ['id' => $stock->id, 'quantity' => 5]);
        $this->assertDatabaseCount('stock_movements', 3);
    }

    #[TestWith(['draft'])]
    #[TestWith(['confirmed'])]
    #[TestWith(['cancelled'])]
    public function test_orders_not_paid_and_delivered_cannot_be_returned(string $status): void
    {
        [$user, $order] = $this->completedOrder();
        $order->completed_at = null;
        $order->status = OrderStatus::from($status);
        $order->save();

        $this->actingAs($user)->post(route('orders.returns.store', [$order->team, $order]), $this->data($order))
            ->assertSessionHasErrors(['order' => 'Solo se pueden devolver pedidos cobrados y entregados.']);

        $this->assertDatabaseCount('order_returns', 0);
        $this->assertDatabaseCount('stock_movements', 1);
    }

    public function test_foreign_order_items_cannot_be_returned_through_another_order(): void
    {
        [$user, $order] = $this->completedOrder();
        $foreign = OrderReturn::factory()->create();

        $this->actingAs($user)->post(route('orders.returns.store', [$order->team, $order]), [...$this->data($order), 'order_item_id' => $foreign->order_item_id])->assertNotFound();

        $this->assertSame(0, $order->returns()->count());
        $this->assertDatabaseCount('stock_movements', 1);
    }

    public function test_return_rolls_back_when_stock_reincorporation_fails(): void
    {
        [$user, $order, $stock] = $this->completedOrder();
        $stock->update(['quantity' => 2147483647]);

        $this->actingAs($user)->post(route('orders.returns.store', [$order->team, $order]), [...$this->data($order), 'restock' => true])->assertSessionHasErrors('quantity');

        $this->assertDatabaseCount('order_returns', 0);
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertDatabaseHas('stocks', ['id' => $stock->id, 'quantity' => 2147483647]);
    }

    public function test_previously_sold_items_can_be_restocked_even_if_catalog_or_branch_is_now_inactive(): void
    {
        [$user, $order, $stock] = $this->completedOrder();
        $order->branch->update(['is_active' => false]);
        $order->items()->sole()->variant->update(['is_active' => false]);

        $this->actingAs($user)->post(route('orders.returns.store', [$order->team, $order]), [...$this->data($order), 'restock' => true])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('stocks', ['id' => $stock->id, 'quantity' => 3]);
    }

    public function test_members_and_guests_cannot_register_returns(): void
    {
        [, $order] = $this->completedOrder();
        $member = User::factory()->create();
        $order->team->members()->attach($member, ['role' => TeamRole::Member->value]);
        $url = route('orders.returns.store', [$order->team, $order]);

        $this->post($url, $this->data($order))->assertRedirectToRoute('login');
        $this->actingAs($member)->post($url, $this->data($order))->assertForbidden();

        $this->assertDatabaseCount('order_returns', 0);
    }

    public function test_another_team_route_cannot_register_the_return(): void
    {
        [$user, $order] = $this->completedOrder();
        $branch = Branch::factory()->create();
        $branch->team->members()->attach($user, ['role' => TeamRole::Admin->value]);

        $this->actingAs($user)->post(route('orders.returns.store', [$branch->team, $order]), $this->data($order))->assertNotFound();

        $this->assertDatabaseCount('order_returns', 0);
    }

    #[TestWith(['quantity', 0])]
    #[TestWith(['quantity', 1.5])]
    #[TestWith(['quantity', 1001])]
    #[TestWith(['restock', 'invalid'])]
    #[TestWith(['reason', ''])]
    #[TestWith(['payment_method', 'invalid'])]
    #[TestWith(['request_token', 'invalid'])]
    public function test_invalid_return_data_is_rejected(string $field, mixed $value): void
    {
        [$user, $order] = $this->completedOrder();

        $this->actingAs($user)->post(route('orders.returns.store', [$order->team, $order]), [...$this->data($order), $field => $value])->assertSessionHasErrors($field);

        $this->assertDatabaseCount('order_returns', 0);
    }

    public function test_public_receipt_shows_refunded_total_without_exposing_reasons_or_refund_references(): void
    {
        [$user, $order] = $this->completedOrder();
        $token = (string) Str::uuid();
        $order->checkout_token = $token;
        $order->save();
        config(['storefront.team_id' => $order->team_id]);
        $this->actingAs($user)->post(route('orders.returns.store', [$order->team, $order]), $this->data($order))->assertSessionHasNoErrors();

        $this->withSession(['storefront_checkout_tokens' => [$token]])->get(route('storefront.checkout.show', $token))
            ->assertInertia(fn (Assert $page) => $page->where('order.refunded_cents', 1099)->missing('order.returns'));
    }

    /** @return array<string, mixed> */
    private function data(Order $order): array
    {
        return ['order_item_id' => $order->items()->sole()->id, 'quantity' => 1, 'restock' => false, 'reason' => 'Cambio de opinión',
            'payment_method' => 'cash', 'payment_reference' => 'REF-123', 'request_token' => (string) Str::uuid()];
    }

    /** @return array{User, Order, Stock} */
    private function completedOrder(): array
    {
        $user = User::factory()->create();
        $branch = Branch::factory()->create();
        $branch->team->members()->attach($user, ['role' => TeamRole::Admin->value]);
        $product = Product::factory()->for($branch->team)->create();
        $variant = ProductVariant::factory()->for($product)->create(['price' => '10.99']);
        $stock = Stock::factory()->create(['team_id' => $branch->team_id, 'branch_id' => $branch->id, 'product_variant_id' => $variant->id, 'quantity' => 5]);
        $order = app(CreateOrder::class)->handle($branch->team, $user, ['branch_id' => $branch->id, 'items' => [['variant_id' => $variant->id, 'quantity' => 3]]]);
        $order = app(ConfirmOrder::class)->handle($order, $user);
        $order = app(CompleteOrder::class)->handle($order, $user, PaymentMethod::Cash, null);

        return [$user, $order, $stock];
    }
}
