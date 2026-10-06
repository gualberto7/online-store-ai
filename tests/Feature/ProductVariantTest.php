<?php

namespace Tests\Feature;

use App\Enums\TeamRole;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class ProductVariantTest extends TestCase
{
    use RefreshDatabase;

    public function test_variants_can_be_created_with_normalized_skus_and_decimal_prices(): void
    {
        [$user, $team, $product] = $this->product();
        $otherProduct = Product::factory()->create();

        $this->actingAs($user)->post(route('products.variants.store', [$team, $product]), [
            'name' => 'Negro / 42', 'sku' => ' zap-42 ', 'price' => '125.50', 'is_active' => true,
            'product_id' => $otherProduct->id, 'team_id' => $otherProduct->team_id,
        ])->assertRedirect(route('products.show', [$team, $product]));

        $this->assertDatabaseHas('product_variants', ['product_id' => $product->id, 'team_id' => $team->id, 'sku' => 'ZAP-42', 'price' => '125.50']);
    }

    public function test_variants_can_be_updated_without_changing_the_sku_or_parent(): void
    {
        [$user, $team, $product] = $this->product();
        $variant = ProductVariant::factory()->for($product)->create(['sku' => 'SKU-1']);

        $this->actingAs($user)->patch(route('products.variants.update', [$team, $product, $variant]), [
            'name' => 'Azul', 'sku' => 'SKU-1', 'price' => '99.99', 'is_active' => false,
        ])->assertRedirect(route('products.show', [$team, $product]));

        $this->assertDatabaseHas('product_variants', ['id' => $variant->id, 'name' => 'Azul', 'sku' => 'SKU-1', 'price' => '99.99', 'is_active' => false]);
    }

    public function test_skus_must_be_unique_across_products_of_the_same_business(): void
    {
        [$user, $team, $product] = $this->product();
        $otherProduct = Product::factory()->for($team)->create();
        ProductVariant::factory()->for($otherProduct)->create(['sku' => 'SKU-1']);

        $this->actingAs($user)->post(route('products.variants.store', [$team, $product]), [
            'name' => 'Estándar', 'sku' => 'sku-1', 'price' => '100', 'is_active' => true,
        ])->assertSessionHasErrors('sku');

        $this->assertDatabaseCount('product_variants', 1);
    }

    public function test_another_business_can_reuse_a_sku(): void
    {
        [$user, $team, $product] = $this->product();
        ProductVariant::factory()->create(['sku' => 'SKU-1']);

        $this->actingAs($user)->post(route('products.variants.store', [$team, $product]), [
            'name' => 'Estándar', 'sku' => 'SKU-1', 'price' => '0', 'is_active' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('product_variants', ['product_id' => $product->id, 'sku' => 'SKU-1', 'price' => 0]);
    }

    #[TestWith(['-1'])]
    #[TestWith(['1.001'])]
    #[TestWith(['10000000000'])]
    #[TestWith(['invalid'])]
    public function test_invalid_prices_are_rejected(string $price): void
    {
        [$user, $team, $product] = $this->product();

        $this->actingAs($user)->post(route('products.variants.store', [$team, $product]), [
            'name' => 'Estándar', 'sku' => 'SKU-1', 'price' => $price, 'is_active' => true,
        ])->assertSessionHasErrors('price');

        $this->assertDatabaseCount('product_variants', 0);
    }

    public function test_required_variant_fields_and_invalid_skus_are_rejected(): void
    {
        [$user, $team, $product] = $this->product();

        $this->actingAs($user)->post(route('products.variants.store', [$team, $product]), [])
            ->assertSessionHasErrors(['name', 'sku', 'price', 'is_active']);
        $this->post(route('products.variants.store', [$team, $product]), [
            'name' => 'Estándar', 'sku' => 'SKU 1', 'price' => '100', 'is_active' => true,
        ])->assertSessionHasErrors('sku');

        $this->assertDatabaseCount('product_variants', 0);
    }

    public function test_variant_forms_can_be_rendered(): void
    {
        [$user, $team, $product] = $this->product();
        $variant = ProductVariant::factory()->for($product)->create();

        $this->actingAs($user)->get(route('products.variants.create', [$team, $product]))
            ->assertInertia(fn (Assert $page) => $page->component('products/variant-form')->where('product.id', $product->id));
        $this->get(route('products.variants.edit', [$team, $product, $variant]))
            ->assertInertia(fn (Assert $page) => $page->component('products/variant-form')->where('variant.id', $variant->id));
    }

    public function test_variants_cannot_be_accessed_through_another_product(): void
    {
        [$user, $team, $product] = $this->product();
        $otherProduct = Product::factory()->for($team)->create();
        $variant = ProductVariant::factory()->for($otherProduct)->create(['name' => 'Original']);

        $this->actingAs($user)->get(route('products.variants.edit', [$team, $product, $variant]))->assertNotFound();
        $this->patch(route('products.variants.update', [$team, $product, $variant]), [
            'name' => 'Nuevo', 'sku' => 'SKU-1', 'price' => '50', 'is_active' => true,
        ])->assertNotFound();

        $this->assertDatabaseHas('product_variants', ['id' => $variant->id, 'name' => 'Original']);
    }

    public function test_members_cannot_manage_variants(): void
    {
        [$user, $team, $product] = $this->product(TeamRole::Member);
        $variant = ProductVariant::factory()->for($product)->create(['name' => 'Original']);
        $payload = ['name' => 'Nuevo', 'sku' => 'SKU-1', 'price' => '50', 'is_active' => true];

        $this->actingAs($user)->get(route('products.variants.create', [$team, $product]))->assertForbidden();
        $this->get(route('products.variants.edit', [$team, $product, $variant]))->assertForbidden();
        $this->post(route('products.variants.store', [$team, $product]), $payload)->assertForbidden();
        $this->patch(route('products.variants.update', [$team, $product, $variant]), $payload)->assertForbidden();

        $this->assertDatabaseHas('product_variants', ['id' => $variant->id, 'name' => 'Original']);
        $this->assertDatabaseCount('product_variants', 1);
    }

    /** @return array{User, Team, Product} */
    private function product(TeamRole $role = TeamRole::Admin): array
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($user, ['role' => $role->value]);

        return [$user, $team, Product::factory()->for($team)->create()];
    }
}
