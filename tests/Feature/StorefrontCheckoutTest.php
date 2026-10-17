<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Stock;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class StorefrontCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cart_uses_live_prices_and_can_update_and_remove_lines(): void
    {
        [$team, $variant] = $this->catalog();
        $this->put(route('storefront.cart.update'), ['variant_id' => $variant->id, 'quantity' => 2, 'price' => '0.01'])->assertRedirectToRoute('storefront.cart.index');
        $variant->update(['price' => '12.35']);

        $this->get(route('storefront.cart.index'))->assertInertia(fn (Assert $page) => $page->component('storefront/cart')
            ->where('cart.items.0.quantity', 2)->where('cart.total_cents', 2470)->where('cartCount', 2));
        $this->put(route('storefront.cart.update'), ['variant_id' => $variant->id, 'quantity' => 0])->assertSessionHasNoErrors();
        $this->get(route('storefront.cart.index'))->assertInertia(fn (Assert $page) => $page->has('cart.items', 0));
    }

    public function test_foreign_and_hidden_variants_cannot_be_added(): void
    {
        [, $variant] = $this->catalog();
        $foreign = ProductVariant::factory()->create();
        $this->put(route('storefront.cart.update'), ['variant_id' => $foreign->id, 'quantity' => 1])->assertNotFound();
        $variant->update(['is_active' => false]);
        $this->put(route('storefront.cart.update'), ['variant_id' => $variant->id, 'quantity' => 1])->assertNotFound();
        $this->assertDatabaseCount('orders', 0);
    }

    #[TestWith([-1])]
    #[TestWith([1001])]
    #[TestWith([1.5])]
    public function test_invalid_cart_quantities_are_rejected(int|float $quantity): void
    {
        [, $variant] = $this->catalog();

        $this->put(route('storefront.cart.update'), ['variant_id' => $variant->id, 'quantity' => $quantity])->assertSessionHasErrors('quantity');
    }

    public function test_guest_checkout_creates_confirmed_order_and_deducts_stock_once(): void
    {
        [$team, $variant, $branch, $stock] = $this->catalog();
        $token = $this->checkoutSession($team, [$variant->id => 2]);

        $this->post(route('storefront.checkout.store'), $this->details($branch, $token))->assertRedirectToRoute('storefront.checkout.show', ['token' => $token])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('orders', ['checkout_token' => $token, 'status' => 'confirmed', 'total_cents' => 2198, 'user_id' => null, 'customer_email' => 'cliente@test.com', 'customer_phone' => '70000000']);
        $this->assertDatabaseHas('stocks', ['id' => $stock->id, 'quantity' => 3]);
        $this->assertDatabaseHas('stock_movements', ['user_id' => null, 'quantity_change' => -2, 'order_id' => Order::query()->sole()->id]);
        $this->get(route('storefront.checkout.show', $token))->assertInertia(fn (Assert $page) => $page->component('storefront/receipt')->where('order.total_cents', 2198)->missing('order.checkout_token')->missing('order.customer_email'));
        $this->get(route('storefront.cart.index'))->assertInertia(fn (Assert $page) => $page->has('cart.items', 0));
        $this->post(route('storefront.checkout.store'), $this->details($branch, $token))->assertRedirectToRoute('storefront.checkout.show', ['token' => $token]);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('stock_movements', 1);
    }

    public function test_empty_checkout_redirects_to_cart_and_cannot_be_submitted(): void
    {
        [$team, , $branch] = $this->catalog();
        $token = $this->checkoutSession($team, []);

        $this->get(route('storefront.checkout.create'))->assertRedirectToRoute('storefront.cart.index');
        $this->post(route('storefront.checkout.store'), $this->details($branch, $token))->assertSessionHasErrors('cart');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_checkout_uses_the_current_price_instead_of_a_submitted_price(): void
    {
        [$team, $variant, $branch] = $this->catalog();
        $token = $this->checkoutSession($team, [$variant->id => 2]);
        $variant->update(['price' => '20.15']);

        $this->post(route('storefront.checkout.store'), [...$this->details($branch, $token), 'total_cents' => 1])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('orders', ['checkout_token' => $token, 'total_cents' => 4030]);
        $this->assertDatabaseHas('order_items', ['unit_price_cents' => 2015, 'quantity' => 2]);
    }

    public function test_retrying_a_completed_checkout_preserves_a_new_cart(): void
    {
        [$team, $variant, $branch] = $this->catalog();
        $token = $this->checkoutSession($team, [$variant->id => 1]);
        $this->post(route('storefront.checkout.store'), $this->details($branch, $token))->assertSessionHasNoErrors();
        $this->put(route('storefront.cart.update'), ['variant_id' => $variant->id, 'quantity' => 2]);

        $this->post(route('storefront.checkout.store'), $this->details($branch, $token))
            ->assertRedirectToRoute('storefront.checkout.show', ['token' => $token]);

        $this->get(route('storefront.cart.index'))->assertInertia(fn (Assert $page) => $page->where('cart.items.0.quantity', 2));
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('stock_movements', 1);
    }

    public function test_insufficient_stock_rolls_back_the_whole_purchase_and_preserves_cart(): void
    {
        [$team, $variant, $branch, $stock] = $this->catalog();
        $other = ProductVariant::factory()->for($variant->product)->create();
        $token = $this->checkoutSession($team, [$variant->id => 1, $other->id => 1]);

        $this->post(route('storefront.checkout.store'), $this->details($branch, $token))->assertSessionHasErrors('quantity');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseHas('stocks', ['id' => $stock->id, 'quantity' => 5]);
        $this->get(route('storefront.cart.index'))->assertInertia(fn (Assert $page) => $page->has('cart.items', 2));
    }

    #[TestWith(['product'])]
    #[TestWith(['category'])]
    #[TestWith(['variant'])]
    public function test_products_hidden_after_being_added_cannot_be_purchased(string $hidden): void
    {
        [$team, $variant, $branch] = $this->catalog();
        $token = $this->checkoutSession($team, [$variant->id => 1]);
        if ($hidden === 'category') {
            $category = Category::factory()->for($team)->create(['is_active' => false]);
            $variant->product->update(['category_id' => $category->id]);
        } elseif ($hidden === 'product') {
            $variant->product->update(['is_active' => false]);
        } else {
            $variant->update(['is_active' => false]);
        }

        $this->post(route('storefront.checkout.store'), $this->details($branch, $token))->assertSessionHasErrors('cart');
        $this->get(route('storefront.cart.index'))->assertInertia(fn (Assert $page) => $page->where('cart.unavailable', true));
        $this->delete(route('storefront.cart.destroy'))->assertRedirectToRoute('storefront.cart.index');
        $this->get(route('storefront.cart.index'))->assertInertia(fn (Assert $page) => $page->where('cart.unavailable', false));
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_another_session_cannot_submit_a_token_or_read_a_receipt(): void
    {
        [$team, $variant, $branch] = $this->catalog();
        $token = $this->checkoutSession($team, [$variant->id => 1]);
        $this->post(route('storefront.checkout.store'), $this->details($branch, $token))->assertSessionHasNoErrors();
        $this->withSession(['storefront_checkout_tokens' => []]);

        $this->get(route('storefront.checkout.show', $token))->assertNotFound();
        $this->post(route('storefront.checkout.store'), $this->details($branch, $token))->assertSessionHasErrors('token');
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_foreign_or_inactive_branches_and_missing_contact_details_are_rejected(): void
    {
        [$team, $variant, $branch] = $this->catalog();
        $token = $this->checkoutSession($team, [$variant->id => 1]);
        $other = Branch::factory()->create();
        $this->post(route('storefront.checkout.store'), $this->details($other, $token))->assertSessionHasErrors('branch_id');
        $branch->update(['is_active' => false]);
        $this->post(route('storefront.checkout.store'), $this->details($branch, $token))->assertSessionHasErrors('branch_id');
        $this->post(route('storefront.checkout.store'), [])->assertSessionHasErrors(['customer_name', 'customer_email', 'customer_phone', 'branch_id', 'token']);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_checkout_page_shows_only_branches_of_the_store_and_current_availability(): void
    {
        [$team, $variant, $branch] = $this->catalog();
        $this->checkoutSession($team, [$variant->id => 2]);
        Branch::factory()->create();
        Branch::factory()->for($team)->create(['is_active' => false]);

        $this->get(route('storefront.checkout.create'))->assertInertia(fn (Assert $page) => $page->component('storefront/checkout')->has('branches', 1)
            ->where('cart.items.0.available_at', [$branch->id])->has('token'));
    }

    /** @return array{Team, ProductVariant, Branch, Stock} */
    private function catalog(): array
    {
        $team = Team::factory()->create();
        config(['storefront.team_id' => $team->id]);
        $product = Product::factory()->for($team)->create();
        $variant = ProductVariant::factory()->for($product)->create(['price' => '10.99']);
        $branch = Branch::factory()->for($team)->create();
        $stock = Stock::factory()->create(['team_id' => $team->id, 'branch_id' => $branch->id, 'product_variant_id' => $variant->id, 'quantity' => 5]);

        return [$team, $variant, $branch, $stock];
    }

    /** @param array<int, int> $items */
    private function checkoutSession(Team $team, array $items): string
    {
        $token = (string) Str::uuid();
        $this->withSession(['storefront_cart' => [$team->id => ['items' => $items]], 'storefront_checkout_tokens' => [$token]]);

        return $token;
    }

    /** @return array<string, int|string> */
    private function details(Branch $branch, string $token): array
    {
        return ['branch_id' => $branch->id, 'token' => $token, 'customer_name' => 'Cliente Demo', 'customer_email' => 'cliente@test.com', 'customer_phone' => '70000000'];
    }
}
