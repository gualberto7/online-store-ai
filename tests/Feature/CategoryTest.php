<?php

namespace Tests\Feature;

use App\Enums\TeamRole;
use App\Models\Category;
use App\Models\Product;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_must_sign_in(): void
    {
        $team = Team::factory()->create();

        $this->get(route('categories.index', $team))->assertRedirect(route('login'));
    }

    public function test_categories_are_scoped_to_the_business_and_count_their_products(): void
    {
        [$user, $team] = $this->member(TeamRole::Member);
        $category = Category::factory()->for($team)->create(['name' => 'Calzado']);
        Product::factory()->for($team)->create(['category_id' => $category->id]);
        Category::factory()->create();

        $this->actingAs($user)->get(route('categories.index', $team))
            ->assertInertia(fn (Assert $page) => $page->component('categories/index')
                ->has('categories', 1)->where('categories.0.name', 'Calzado')->where('categories.0.products_count', 1)->where('canManage', false));
    }

    #[TestWith(['owner', true])]
    #[TestWith(['admin', true])]
    #[TestWith(['member', false])]
    public function test_category_permissions_follow_business_roles(string $role, bool $allowed): void
    {
        [$user, $team] = $this->member(TeamRole::from($role));
        $category = Category::factory()->for($team)->create();
        $other = Category::factory()->create();

        $this->assertTrue($user->can('viewAny', [Category::class, $team]));
        $this->assertSame($allowed, $user->can('create', [Category::class, $team]));
        $this->assertSame($allowed, $user->can('update', $category));
        $this->assertFalse($user->can('update', $other));
    }

    public function test_categories_can_be_created_and_updated_without_overriding_the_business(): void
    {
        [$user, $team] = $this->member(TeamRole::Admin);
        $other = Team::factory()->create();

        $this->actingAs($user)->post(route('categories.store', $team), [
            'name' => 'Calzado', 'description' => 'Para caminar', 'is_active' => true, 'team_id' => $other->id,
        ])->assertRedirect(route('categories.index', $team));
        $category = $team->categories()->sole();
        $this->patch(route('categories.update', [$team, $category]), [
            'name' => 'Calzado', 'description' => null, 'is_active' => false,
        ])->assertRedirect(route('categories.index', $team));

        $this->assertDatabaseHas('categories', ['id' => $category->id, 'team_id' => $team->id, 'name' => 'Calzado', 'description' => null, 'is_active' => false]);
    }

    public function test_names_are_unique_only_within_the_business(): void
    {
        [$user, $team] = $this->member(TeamRole::Admin);
        Category::factory()->create(['name' => 'Calzado']);

        $payload = ['name' => 'Calzado', 'is_active' => true];
        $this->actingAs($user)->post(route('categories.store', $team), $payload)->assertRedirect();
        $this->post(route('categories.store', $team), $payload)->assertSessionHasErrors('name');

        $this->assertSame(1, $team->categories()->count());
    }

    public function test_category_management_forms_render_for_administrators(): void
    {
        [$user, $team] = $this->member(TeamRole::Admin);
        $category = Category::factory()->for($team)->create();

        $this->actingAs($user)->get(route('categories.create', $team))
            ->assertInertia(fn (Assert $page) => $page->component('categories/form'));
        $this->get(route('categories.edit', [$team, $category]))
            ->assertInertia(fn (Assert $page) => $page->component('categories/form')->where('category.id', $category->id));
    }

    public function test_members_cannot_manage_categories(): void
    {
        [$user, $team] = $this->member(TeamRole::Member);
        $category = Category::factory()->for($team)->create(['name' => 'Original']);

        $this->actingAs($user)->get(route('categories.create', $team))->assertForbidden();
        $this->get(route('categories.edit', [$team, $category]))->assertForbidden();
        $this->post(route('categories.store', $team), ['name' => 'Nuevo', 'is_active' => true])->assertForbidden();
        $this->patch(route('categories.update', [$team, $category]), ['name' => 'Nuevo', 'is_active' => true])->assertForbidden();

        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'Original']);
        $this->assertDatabaseCount('categories', 1);
    }

    public function test_cross_business_categories_are_not_found(): void
    {
        [$user, $team] = $this->member(TeamRole::Admin);
        $category = Category::factory()->create();

        $this->actingAs($user)->get(route('categories.edit', [$team, $category]))->assertNotFound();
        $this->patch(route('categories.update', [$team, $category]), ['name' => 'Otro', 'is_active' => true])->assertNotFound();
    }

    public function test_invalid_category_fields_are_rejected(): void
    {
        [$user, $team] = $this->member(TeamRole::Admin);

        $this->actingAs($user)->post(route('categories.store', $team), [
            'name' => '', 'description' => str_repeat('a', 2001), 'is_active' => 'invalid',
        ])->assertSessionHasErrors(['name', 'description', 'is_active']);

        $this->assertDatabaseCount('categories', 0);
    }

    public function test_products_can_be_assigned_and_removed_from_a_category(): void
    {
        [$user, $team] = $this->member(TeamRole::Admin);
        $category = Category::factory()->for($team)->create();
        $payload = ['name' => 'Zapatillas', 'is_active' => true, 'category_id' => $category->id];

        $this->actingAs($user)->post(route('products.store', $team), $payload)->assertRedirect();
        $product = $team->products()->sole();
        $this->assertSame($category->id, $product->category_id);
        $this->patch(route('products.update', [$team, $product]), [...$payload, 'category_id' => ''])->assertRedirect();

        $this->assertNull($product->fresh()->category_id);
    }

    public function test_products_cannot_be_assigned_a_category_from_another_business(): void
    {
        [$user, $team] = $this->member(TeamRole::Admin);
        $otherCategory = Category::factory()->create();

        $this->actingAs($user)->post(route('products.store', $team), [
            'name' => 'Producto', 'is_active' => true, 'category_id' => $otherCategory->id,
        ])->assertSessionHasErrors('category_id');

        $this->assertDatabaseCount('products', 0);
    }

    public function test_product_forms_show_only_categories_of_the_business(): void
    {
        [$user, $team] = $this->member(TeamRole::Admin);
        Category::factory()->for($team)->create(['name' => 'Calzado']);
        Category::factory()->create();

        $this->actingAs($user)->get(route('products.create', $team))
            ->assertInertia(fn (Assert $page) => $page->has('categories', 1)->where('categories.0.name', 'Calzado'));
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
