<?php

namespace Tests\Feature;

use App\Actions\ConfirmOrder;
use App\Actions\CreateOrder;
use App\Enums\OrderStatus;
use App\Enums\TeamRole;
use App\Models\Branch;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Stock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class OrderCompletionTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith(['cash'])]
    #[TestWith(['card'])]
    #[TestWith(['transfer'])]
    public function test_admin_can_record_full_payment_and_collection_without_deducting_stock_again(string $method): void
    {
        [$user, $order, $stock] = $this->confirmedOrder();
        $this->travelTo(now()->startOfSecond());

        $this->actingAs($user)->post(route('orders.complete', [$order->team, $order]), [
            'payment_method' => $method, 'payment_reference' => 'OP-123', 'total_cents' => 1, 'completed_by_user_id' => 999,
        ])->assertRedirectToRoute('orders.show', ['current_team' => $order->team, 'order' => $order]);

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'confirmed', 'payment_method' => $method,
            'payment_reference' => 'OP-123', 'completed_by_user_id' => $user->id, 'total_cents' => 2198]);
        $this->assertSame(now()->toDateTimeString(), $order->fresh()->completed_at->toDateTimeString());
        $this->assertDatabaseHas('stocks', ['id' => $stock->id, 'quantity' => 3]);
        $this->assertDatabaseCount('stock_movements', 1);
        $this->get(route('orders.show', [$order->team, $order]))->assertInertia(fn (Assert $page) => $page
            ->where('order.payment_method', $method)->where('order.completed_by.name', $user->name));
    }

    public function test_repeated_collection_preserves_original_method_reference_actor_and_time(): void
    {
        [$user, $order] = $this->confirmedOrder();
        $url = route('orders.complete', [$order->team, $order]);
        $this->actingAs($user)->post($url, ['payment_method' => 'cash', 'payment_reference' => 'ORIGINAL'])->assertSessionHasNoErrors();
        $completedAt = $order->fresh()->completed_at;
        $this->travel(1)->hours();
        $other = User::factory()->create();
        $order->team->members()->attach($other, ['role' => TeamRole::Admin->value]);

        $this->actingAs($other)->post($url, ['payment_method' => 'card', 'payment_reference' => 'NEW'])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_method' => 'cash', 'payment_reference' => 'ORIGINAL', 'completed_by_user_id' => $user->id]);
        $this->assertSame($completedAt->toDateTimeString(), $order->fresh()->completed_at->toDateTimeString());
        $this->assertDatabaseCount('stock_movements', 1);
    }

    #[TestWith(['draft'])]
    #[TestWith(['cancelled'])]
    public function test_only_confirmed_orders_can_be_collected(string $status): void
    {
        [$user, $order] = $this->confirmedOrder();
        $order->status = OrderStatus::from($status);
        $order->save();

        $this->actingAs($user)->post(route('orders.complete', [$order->team, $order]), ['payment_method' => 'cash'])
            ->assertSessionHasErrors(['order' => 'Solo se pueden cobrar y entregar pedidos confirmados.']);

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'completed_at' => null, 'payment_method' => null]);
    }

    public function test_collected_orders_cannot_be_cancelled_and_return_stock(): void
    {
        [$user, $order, $stock] = $this->confirmedOrder();
        $this->actingAs($user)->post(route('orders.complete', [$order->team, $order]), ['payment_method' => 'cash'])->assertSessionHasNoErrors();

        $this->post(route('orders.cancel', [$order->team, $order]), ['reason' => 'Cancelar'])
            ->assertSessionHasErrors(['order' => 'Un pedido cobrado y entregado requiere una devolución; no puede cancelarse.']);

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'confirmed', 'cancelled_at' => null]);
        $this->assertDatabaseHas('stocks', ['id' => $stock->id, 'quantity' => 3]);
        $this->assertDatabaseCount('stock_movements', 1);
    }

    public function test_invalid_payment_details_do_not_complete_an_order(): void
    {
        [$user, $order] = $this->confirmedOrder();
        $url = route('orders.complete', [$order->team, $order]);

        $this->actingAs($user)->post($url, [])->assertSessionHasErrors('payment_method');
        $this->post($url, ['payment_method' => 'invalid', 'payment_reference' => str_repeat('x', 256)])
            ->assertSessionHasErrors(['payment_method', 'payment_reference']);

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'completed_at' => null, 'payment_method' => null, 'payment_reference' => null]);
    }

    public function test_members_and_guests_cannot_register_a_payment(): void
    {
        [, $order] = $this->confirmedOrder();
        $member = User::factory()->create();
        $order->team->members()->attach($member, ['role' => TeamRole::Member->value]);
        $url = route('orders.complete', [$order->team, $order]);

        $this->post($url, ['payment_method' => 'cash'])->assertRedirectToRoute('login');
        $this->actingAs($member)->post($url, ['payment_method' => 'cash'])->assertForbidden();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'completed_at' => null]);
    }

    public function test_an_order_cannot_be_completed_through_another_teams_route(): void
    {
        [$user, $order] = $this->confirmedOrder();
        $other = Branch::factory()->create();
        $other->team->members()->attach($user, ['role' => TeamRole::Admin->value]);

        $this->actingAs($user)->post(route('orders.complete', [$other->team, $order]), ['payment_method' => 'cash'])->assertNotFound();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'completed_at' => null]);
    }

    public function test_public_receipt_shows_collection_without_exposing_payment_reference_or_operator(): void
    {
        [$user, $order] = $this->confirmedOrder();
        $token = (string) Str::uuid();
        $order->checkout_token = $token;
        $order->save();
        config(['storefront.team_id' => $order->team_id]);
        $this->actingAs($user)->post(route('orders.complete', [$order->team, $order]), ['payment_method' => 'transfer', 'payment_reference' => 'PRIVATE'])->assertSessionHasNoErrors();

        $this->withSession(['storefront_checkout_tokens' => [$token]])->get(route('storefront.checkout.show', $token))
            ->assertInertia(fn (Assert $page) => $page->where('order.completed_at', $order->fresh()->completed_at->toIso8601String())
                ->missing('order.payment_reference')->missing('order.completed_by_user_id'));
    }

    /** @return array{User, Order, Stock} */
    private function confirmedOrder(): array
    {
        $user = User::factory()->create();
        $branch = Branch::factory()->create();
        $branch->team->members()->attach($user, ['role' => TeamRole::Admin->value]);
        $product = Product::factory()->for($branch->team)->create();
        $variant = ProductVariant::factory()->for($product)->create(['price' => '10.99']);
        $stock = Stock::factory()->create(['team_id' => $branch->team_id, 'branch_id' => $branch->id, 'product_variant_id' => $variant->id, 'quantity' => 5]);
        $order = app(CreateOrder::class)->handle($branch->team, $user, ['branch_id' => $branch->id, 'items' => [['variant_id' => $variant->id, 'quantity' => 2]]]);
        $order = app(ConfirmOrder::class)->handle($order, $user);

        return [$user, $order, $stock];
    }
}
