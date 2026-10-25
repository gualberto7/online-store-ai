<?php

namespace Tests\Feature;

use App\Actions\SearchCatalogSemantically;
use App\Enums\ProductAudience;
use App\Enums\TeamRole;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class ProductAudienceTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith(['women', ['women', 'unisex']])]
    #[TestWith(['men', ['men', 'unisex']])]
    #[TestWith(['unisex', ['unisex']])]
    public function test_public_filter_excludes_other_audiences(string $audience, array $expected): void
    {
        $team = $this->catalog();
        foreach (['women', 'men', 'unisex'] as $value) {
            $product = Product::factory()->for($team)->create(['name' => $value, 'audience' => $value]);
            ProductVariant::factory()->for($product)->create();
        }

        $this->get(route('home', ['audience' => $audience]))
            ->assertInertia(fn (Assert $page) => $page->where('filters.audience', $audience)
                ->where('products.total', count($expected))
                ->where('products.data', fn ($products) => collect($products)->pluck('name')->sort()->values()->all() === collect($expected)->sort()->values()->all()));
    }

    public function test_ai_audience_is_a_hard_filter_even_when_semantic_matches_include_men(): void
    {
        $team = $this->catalog();
        $ids = [];
        foreach (['women', 'men', 'unisex'] as $value) {
            $product = Product::factory()->for($team)->create(['audience' => $value]);
            ProductVariant::factory()->for($product)->create();
            $ids[] = $product->id;
        }
        $this->mock(SearchCatalogSemantically::class, function ($mock) use ($ids): void {
            $mock->shouldReceive('available')->andReturn(true);
            $mock->shouldReceive('handle')->once()->withArgs(fn ($team, $query, $category, $branch, $available, $audience) => $audience === ProductAudience::Women)
                ->andReturn(['ids' => $ids, 'fallback' => false]);
        });

        $this->get(route('home', ['ai_query' => 'Busco un regalo para mujer']))
            ->assertInertia(fn (Assert $page) => $page->where('products.total', 2)
                ->where('products.data', fn ($products) => ! collect($products)->pluck('id')->contains($ids[1])));
    }

    public function test_managers_can_set_and_edit_audience_and_invalid_values_are_rejected(): void
    {
        $team = $this->catalog();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
        $this->actingAs($user)->post(route('products.store', $team), ['name' => 'Reloj', 'is_active' => true, 'audience' => 'men'])->assertSessionHasNoErrors();
        $product = $team->products()->sole();
        $this->assertDatabaseHas('products', ['id' => $product->id, 'audience' => 'men']);

        $this->patch(route('products.update', [$team, $product]), ['name' => 'Reloj', 'is_active' => true, 'audience' => 'women'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('products', ['id' => $product->id, 'audience' => 'women']);
        $this->patch(route('products.update', [$team, $product]), ['name' => 'Reloj', 'is_active' => true, 'audience' => 'unknown'])->assertSessionHasErrors('audience');
        $this->get(route('home', ['audience' => 'unknown']))->assertSessionHasErrors('audience');
    }

    public function test_backfill_uses_categories_and_demo_metadata_and_preserves_manual_values(): void
    {
        $team = $this->catalog();
        $category = Category::factory()->for($team)->create(['name' => 'Relojes para hombre']);
        $men = Product::factory()->for($team)->for($category)->create(['audience' => null]);
        $women = Product::factory()->for($team)->create(['name' => 'Bolso Prada', 'audience' => null]);
        $neutral = Product::factory()->for($team)->create(['name' => 'Mochila urbana', 'audience' => null]);
        $manual = Product::factory()->for($team)->for($category)->create(['audience' => 'unisex']);

        $this->artisan('catalog:backfill-audience')->assertSuccessful();
        $this->assertDatabaseHas('products', ['id' => $men->id, 'audience' => 'men']);
        $this->assertDatabaseHas('products', ['id' => $women->id, 'audience' => 'women']);
        $this->assertDatabaseHas('products', ['id' => $neutral->id, 'audience' => 'unisex']);
        $this->assertDatabaseHas('products', ['id' => $manual->id, 'audience' => 'unisex']);
        $this->artisan('catalog:backfill-audience')->expectsOutput('Productos clasificados: 0.')->assertSuccessful();
    }

    #[TestWith(['Para mujeres', 'women'])]
    #[TestWith(['Accesorios para hombre', 'men'])]
    #[TestWith(['Algo unisex', 'unisex'])]
    #[TestWith(['Para mujer y hombre', null])]
    #[TestWith(['Para viajar', null])]
    public function test_query_recognizes_explicit_audiences(string $query, ?string $expected): void
    {
        $this->assertSame($expected, ProductAudience::fromQuery($query)?->value);
    }

    private function catalog(): Team
    {
        config(['semantic-search.enabled' => false]);
        $team = Team::factory()->create(['slug' => 'tienda-demo']);
        config(['storefront.team_id' => $team->id]);

        return $team;
    }
}
