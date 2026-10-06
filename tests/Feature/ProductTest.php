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

class ProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_must_sign_in_to_view_the_catalog(): void
    {
        $team = Team::factory()->create();

        $this->get(route('products.index', $team))->assertRedirect(route('login'));
    }

    public function test_catalog_is_paginated_and_scoped_to_the_business(): void
    {
        [$user, $team] = $this->member(TeamRole::Member);
        Product::factory()->for($team)->count(25)->create();
        Product::factory()->create(['name' => 'Otro negocio']);

        $this->actingAs($user)->get(route('products.index', $team))
            ->assertInertia(fn (Assert $page) => $page->component('products/index')
                ->has('products.data', 24)->where('products.total', 25)->where('canManage', false));
    }

    #[TestWith(['owner', true])]
    #[TestWith(['admin', true])]
    #[TestWith(['member', false])]
    public function test_product_permissions_follow_business_roles(string $role, bool $allowed): void
    {
        [$user, $team] = $this->member(TeamRole::from($role));
        $product = Product::factory()->for($team)->create();
        $otherProduct = Product::factory()->create();

        $this->assertTrue($user->can('view', $product));
        $this->assertSame($allowed, $user->can('create', [Product::class, $team]));
        $this->assertSame($allowed, $user->can('update', $product));
        $this->assertFalse($user->can('view', $otherProduct));
        $this->assertFalse($user->can('update', $otherProduct));
    }

    public function test_products_can_be_created_without_overriding_the_business(): void
    {
        [$user, $team] = $this->member(TeamRole::Admin);
        $otherTeam = Team::factory()->create();

        $response = $this->actingAs($user)->post(route('products.store', $team), [
            'name' => 'Zapatillas', 'description' => 'Para caminar', 'is_active' => true, 'team_id' => $otherTeam->id,
        ]);

        $product = $team->products()->sole();
        $response->assertRedirect(route('products.show', [$team, $product]));
        $this->assertDatabaseHas('products', ['id' => $product->id, 'team_id' => $team->id, 'description' => 'Para caminar']);
    }

    public function test_products_can_be_edited_and_deactivated(): void
    {
        [$user, $team] = $this->member(TeamRole::Owner);
        $product = Product::factory()->for($team)->create();

        $this->actingAs($user)->patch(route('products.update', [$team, $product]), [
            'name' => 'Zapatillas', 'description' => null, 'is_active' => false,
        ])->assertRedirect(route('products.show', [$team, $product]));

        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'Zapatillas', 'description' => null, 'is_active' => false]);
    }

    public function test_members_can_view_variants_but_cannot_manage_products(): void
    {
        [$user, $team] = $this->member(TeamRole::Member);
        $product = Product::factory()->for($team)->create(['name' => 'Original']);
        ProductVariant::factory()->for($product)->create(['price' => '125.50']);

        $this->actingAs($user)->get(route('products.show', [$team, $product]))
            ->assertInertia(fn (Assert $page) => $page->component('products/show')
                ->has('variants', 1)->where('variants.0.price', '125.50')->where('canManage', false));
        $this->get(route('products.create', $team))->assertForbidden();
        $this->get(route('products.edit', [$team, $product]))->assertForbidden();
        $this->post(route('products.store', $team), ['name' => 'Nuevo', 'is_active' => true])->assertForbidden();
        $this->patch(route('products.update', [$team, $product]), ['name' => 'Nuevo', 'is_active' => true])->assertForbidden();

        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'Original']);
        $this->assertDatabaseCount('products', 1);
    }

    public function test_management_forms_render_for_administrators(): void
    {
        [$user, $team] = $this->member(TeamRole::Admin);
        $product = Product::factory()->for($team)->create();

        $this->actingAs($user)->get(route('products.create', $team))->assertInertia(fn (Assert $page) => $page->component('products/form'));
        $this->get(route('products.edit', [$team, $product]))->assertInertia(fn (Assert $page) => $page->component('products/form')->where('product.id', $product->id));
    }

    public function test_cross_business_products_are_not_found(): void
    {
        [$user, $team] = $this->member(TeamRole::Owner);
        $product = Product::factory()->create(['name' => 'Original']);

        $this->actingAs($user)->get(route('products.show', [$team, $product]))->assertNotFound();
        $this->get(route('products.edit', [$team, $product]))->assertNotFound();
        $this->patch(route('products.update', [$team, $product]), ['name' => 'Nuevo', 'is_active' => true])->assertNotFound();

        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'Original']);
    }

    public function test_invalid_products_are_not_saved(): void
    {
        [$user, $team] = $this->member(TeamRole::Owner);

        $this->actingAs($user)->post(route('products.store', $team), [
            'name' => '', 'description' => str_repeat('a', 10001), 'is_active' => 'invalid',
        ])->assertSessionHasErrors(['name', 'description', 'is_active']);

        $this->assertDatabaseCount('products', 0);
    }

    /** @return array{User, Team} */
    private function member(TeamRole $role): array
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($user, ['role' => $role->value]);

        return [$user, $team];
    }
}
