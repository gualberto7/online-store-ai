<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Stock;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class StorefrontTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_browse_only_the_configured_business_catalog(): void
    {
        $team = $this->store();
        $product = $this->product($team, 'Zapatillas');
        $this->product(Team::factory()->create(), 'Producto ajeno');

        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->component('storefront/index')
            ->where('storefront.name', 'Tienda Demo')->has('products.data', 1)->where('products.data.0.id', $product->id)
            ->where('products.data.0.price_from', '100.00')->where('auth.user', null));
    }

    public function test_unconfigured_stores_do_not_publish_a_business_automatically(): void
    {
        $team = Team::factory()->create();
        $product = $this->product($team, 'Privado');
        config(['storefront.team_id' => null]);

        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->has('products.data', 0));
        $this->get(route('storefront.show', $product))->assertNotFound();
    }

    public function test_personal_teams_cannot_be_published_as_a_store(): void
    {
        $team = Team::factory()->personal()->create();
        $product = $this->product($team, 'Privado');
        config(['storefront.team_id' => $team->id]);

        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->has('products.data', 0));
        $this->get(route('storefront.show', $product))->assertNotFound();
    }

    #[TestWith(['product'])]
    #[TestWith(['variant'])]
    #[TestWith(['category'])]
    public function test_inactive_catalog_entries_are_not_public(string $inactive): void
    {
        $team = $this->store();
        $category = Category::factory()->for($team)->create();
        $product = $this->product($team, 'Oculto');
        $product->update(['category_id' => $category->id]);
        match ($inactive) {
            'product' => $product->update(['is_active' => false]),
            'variant' => $product->variants()->update(['is_active' => false]),
            'category' => $category->update(['is_active' => false]),
        };

        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->has('products.data', 0));
        $this->get(route('storefront.show', $product))->assertNotFound();
    }

    public function test_products_without_variants_are_not_published(): void
    {
        $team = $this->store();
        $product = Product::factory()->for($team)->create();

        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->has('products.data', 0));
        $this->get(route('storefront.show', $product))->assertNotFound();
    }

    public function test_search_matches_product_descriptions_and_active_skus_without_leaking_other_businesses(): void
    {
        $team = $this->store();
        $product = $this->product($team, 'Zapatillas');
        $product->update(['description' => 'Para caminar']);
        $product->variants()->update(['sku' => 'ZAP-42']);
        $this->product(Team::factory()->create(), 'Para caminar');
        $this->product($team, 'Mochila');

        $this->get(route('home', ['q' => 'caminar']))
            ->assertInertia(fn (Assert $page) => $page->has('products.data', 1)->where('products.data.0.id', $product->id));
        $this->get(route('home', ['q' => 'zap-42']))
            ->assertInertia(fn (Assert $page) => $page->has('products.data', 1)->where('products.data.0.id', $product->id));
    }

    public function test_products_can_be_filtered_by_category(): void
    {
        $team = $this->store();
        $category = Category::factory()->for($team)->create(['name' => 'Calzado']);
        $product = $this->product($team, 'Zapatillas');
        $product->update(['category_id' => $category->id]);
        $this->product($team, 'Mochila');

        $this->get(route('home', ['category' => $category->id]))
            ->assertInertia(fn (Assert $page) => $page->has('products.data', 1)->where('products.data.0.category', 'Calzado'));
    }

    public function test_starting_prices_ignore_inactive_variants_and_sort_numerically(): void
    {
        $team = $this->store();
        $expensive = $this->product($team, 'A caro', '100.00');
        ProductVariant::factory()->for($expensive)->create(['price' => '1.00', 'is_active' => false]);
        $cheap = $this->product($team, 'Z barato', '9.99');

        $this->get(route('home', ['sort' => 'price_asc']))
            ->assertInertia(fn (Assert $page) => $page->where('products.data.0.id', $cheap->id)->where('products.data.1.price_from', '100.00'));
        $this->get(route('home', ['sort' => 'price_desc']))
            ->assertInertia(fn (Assert $page) => $page->where('products.data.0.id', $expensive->id));
    }

    public function test_availability_filters_use_only_active_branches_and_variants(): void
    {
        $team = $this->store();
        $product = $this->product($team, 'Zapatillas');
        $variant = $product->variants()->sole();
        $inactiveBranch = Branch::factory()->for($team)->create(['is_active' => false]);
        Stock::factory()->create(['branch_id' => $inactiveBranch->id, 'team_id' => $team->id, 'product_variant_id' => $variant->id, 'quantity' => 10]);

        $this->get(route('home', ['available' => 1]))
            ->assertInertia(fn (Assert $page) => $page->has('products.data', 0));
        $branch = Branch::factory()->for($team)->create();
        Stock::factory()->create(['branch_id' => $branch->id, 'team_id' => $team->id, 'product_variant_id' => $variant->id, 'quantity' => 5]);
        $this->get(route('home', ['available' => 1]))
            ->assertInertia(fn (Assert $page) => $page->has('products.data', 1)->where('products.data.0.available', true));
    }

    public function test_availability_can_be_checked_for_a_specific_branch(): void
    {
        $team = $this->store();
        $product = $this->product($team, 'Zapatillas');
        $variant = $product->variants()->sole();
        $branch = Branch::factory()->for($team)->create();
        $emptyBranch = Branch::factory()->for($team)->create();
        Stock::factory()->create(['branch_id' => $branch->id, 'team_id' => $team->id, 'product_variant_id' => $variant->id, 'quantity' => 5]);

        $this->get(route('home', ['branch' => $emptyBranch->id, 'available' => 1]))
            ->assertInertia(fn (Assert $page) => $page->has('products.data', 0));
        $this->get(route('home', ['branch' => $branch->id, 'available' => 1]))
            ->assertInertia(fn (Assert $page) => $page->has('products.data', 1));
    }

    public function test_public_product_details_expose_only_active_variants_and_availability_not_stock_quantities(): void
    {
        $team = $this->store();
        $product = $this->product($team, 'Zapatillas');
        $variant = $product->variants()->sole();
        ProductVariant::factory()->for($product)->create(['is_active' => false]);
        $branch = Branch::factory()->for($team)->create();
        Branch::factory()->for($team)->create(['is_active' => false]);
        Branch::factory()->create();
        Stock::factory()->create(['branch_id' => $branch->id, 'team_id' => $team->id, 'product_variant_id' => $variant->id, 'quantity' => 50]);
        $product->images()->create(['path' => 'test.jpg']);

        $this->get(route('storefront.show', $product))
            ->assertInertia(fn (Assert $page) => $page->component('storefront/show')
                ->has('product.variants', 1)->where('product.variants.0.available_at', [$branch->id])
                ->has('branches', 1)->has('product.images', 1)
                ->missing('product.team_id')->missing('product.variants.0.stocks')->missing('product.images.0.path'));
    }

    public function test_another_business_product_cannot_be_opened_publicly(): void
    {
        $this->store();
        $product = $this->product(Team::factory()->create(), 'Privado');

        $this->get(route('storefront.show', $product))->assertNotFound();
    }

    public function test_a_signed_in_visitors_current_team_does_not_change_the_public_business(): void
    {
        $team = $this->store();
        $this->product($team, 'Público');
        $user = User::factory()->create();
        $this->product($user->personalTeam(), 'Privado');

        $this->actingAs($user)->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page->has('products.data', 1)->where('products.data.0.name', 'Público'));
    }

    public function test_pagination_preserves_the_search_filters(): void
    {
        $team = $this->store();
        for ($index = 0; $index < 13; $index++) {
            $this->product($team, "Producto $index");
        }

        $this->get(route('home', ['q' => 'Producto', 'sort' => 'price_asc']))
            ->assertInertia(fn (Assert $page) => $page->has('products.data', 12)->where('products.total', 13)
                ->where('products.next_page_url', fn (string $url): bool => str_contains($url, 'q=Producto') && str_contains($url, 'sort=price_asc')));
    }

    public function test_invalid_filters_and_foreign_category_or_branch_ids_are_rejected(): void
    {
        $this->store();
        $otherCategory = Category::factory()->create();
        $otherBranch = Branch::factory()->create();

        $this->get(route('home', ['category' => $otherCategory->id, 'branch' => $otherBranch->id, 'sort' => 'invalid', 'q' => str_repeat('a', 121)]))
            ->assertSessionHasErrors(['category', 'branch', 'sort', 'q']);
    }

    public function test_stock_on_inactive_variants_does_not_make_a_product_available(): void
    {
        $team = $this->store();
        $product = $this->product($team, 'Zapatillas');
        $inactiveVariant = ProductVariant::factory()->for($product)->create(['is_active' => false]);
        $branch = Branch::factory()->for($team)->create();
        Stock::factory()->create(['branch_id' => $branch->id, 'team_id' => $team->id, 'product_variant_id' => $inactiveVariant->id, 'quantity' => 10]);

        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->where('products.data.0.available', false));
        $this->get(route('home', ['available' => 1]))->assertInertia(fn (Assert $page) => $page->has('products.data', 0));
    }

    public function test_archived_businesses_are_not_published(): void
    {
        $team = $this->store();
        $product = $this->product($team, 'Privado');
        $team->delete();

        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->has('products.data', 0));
        $this->get(route('storefront.show', $product))->assertNotFound();
    }

    private function store(): Team
    {
        $team = Team::factory()->create();
        config(['storefront.team_id' => $team->id, 'storefront.name' => 'Tienda Demo']);

        return $team;
    }

    private function product(Team $team, string $name, string $price = '100.00'): Product
    {
        $product = Product::factory()->for($team)->create(['name' => $name]);
        ProductVariant::factory()->for($product)->create(['price' => $price]);

        return $product;
    }
}
