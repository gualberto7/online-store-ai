<?php

namespace Tests\Feature;

use App\Actions\CatalogPriceFilter;
use App\Actions\GenerateSearchEmbedding;
use App\Actions\SearchCatalogSemantically;
use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Stock;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Ai\Embeddings;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class CatalogPriceFilterTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith(['productos de deporte con precio menor a 200 bs', [['operator' => '<', 'price' => '200.00']]])]
    #[TestWith(['mochilas hasta 200 Bs', [['operator' => '<=', 'price' => '200.00']]])]
    #[TestWith(['ropa menos de 199,90 bolivianos', [['operator' => '<', 'price' => '199.90']]])]
    #[TestWith(['regalos con presupuesto de 1.200,50 Bs', [['operator' => '<=', 'price' => '1200.50']]])]
    #[TestWith(['regalos hasta 1,200.50 BOB', [['operator' => '<=', 'price' => '1200.50']]])]
    #[TestWith(['regalos hasta 1 200 Bs', [['operator' => '<=', 'price' => '1200.00']]])]
    #[TestWith(['mochilas entre 100 y 200 Bs', [['operator' => '>=', 'price' => '100.00'], ['operator' => '<=', 'price' => '200.00']]])]
    #[TestWith(['ropa mayor a 100 y menor a 200 Bs', [['operator' => '>', 'price' => '100.00'], ['operator' => '<', 'price' => '200.00']]])]
    #[TestWith(['ropa al menos 100 Bs', [['operator' => '>=', 'price' => '100.00']]])]
    #[TestWith(['ropa menor o igual a 200 Bs', [['operator' => '<=', 'price' => '200.00']]])]
    #[TestWith(['ropa > 200 Bs', [['operator' => '>', 'price' => '200.00']]])]
    #[TestWith(['ropa <= 200 Bs', [['operator' => '<=', 'price' => '200.00']]])]
    #[TestWith(['regalos hasta 0 Bs', [['operator' => '<=', 'price' => '0.00']]])]
    #[TestWith(['SKU-200 para correr', []])]
    #[TestWith(['regalos para menores de 12 años', []])]
    #[TestWith(['paquetes de menos de 20 unidades', []])]
    public function test_price_phrases_preserve_comparison_boundaries_and_currency_precision(string $query, array $expected): void
    {
        $this->assertSame($expected, CatalogPriceFilter::fromQuery($query)->constraints);
    }

    public function test_ai_suggestions_cannot_bypass_the_budget_and_show_the_matching_variant_price(): void
    {
        $team = $this->store();
        $cheap = $this->product($team, 'Deporte económico', '199.99');
        $equal = $this->product($team, 'Deporte límite', '200.00');
        $expensive = $this->product($team, 'Deporte caro', '300.00');
        $mixed = $this->product($team, 'Deporte mixto', '250.00');
        $affordable = ProductVariant::factory()->for($mixed)->create(['name' => 'Z barata', 'price' => '150.00']);
        $inactive = $this->product($team, 'Solo variante cara activa', '300.00');
        ProductVariant::factory()->for($inactive)->create(['price' => '10.00', 'is_active' => false]);
        $ids = [$expensive->id, $equal->id, $cheap->id, $mixed->id, $inactive->id];
        $this->mock(SearchCatalogSemantically::class, function ($mock) use ($ids): void {
            $mock->shouldReceive('available')->andReturn(true);
            $mock->shouldReceive('handle')->once()->withArgs(fn ($team, $query, $category, $branch, $available, $audience, $budget) => $query === 'productos de deporte' && $budget->constraints === [['operator' => '<', 'price' => '200.00']])
                ->andReturn(['ids' => $ids, 'fallback' => false]);
        });
        $query = 'productos de deporte con precio menor a 200 bs';

        $this->get(route('home', ['ai_query' => $query]))->assertInertia(fn (Assert $page) => $page
            ->where('products.total', 2)->where('products.data.0.id', $cheap->id)->where('products.data.0.price_from', '199.99')
            ->where('products.data.1.id', $mixed->id)->where('products.data.1.price_from', '150.00')
            ->where('semanticSearch.price_filter', 'Menos de 200,00 Bs'));
        $this->get(route('storefront.show', ['product' => $mixed, 'ai_query' => $query]))
            ->assertInertia(fn (Assert $page) => $page->where('matchingVariantId', $affordable->id)->where('catalogFilters.ai_query', $query));
    }

    public function test_range_prices_stock_and_availability_must_match_the_same_variant(): void
    {
        $team = $this->store();
        $branch = Branch::factory()->for($team)->create();
        $mixed = $this->product($team, 'Deporte mixto', '50.00');
        $inRange = ProductVariant::factory()->for($mixed)->create(['price' => '150.00']);
        Stock::factory()->create(['team_id' => $team->id, 'branch_id' => $branch->id, 'product_variant_id' => $mixed->variants()->orderBy('id')->first()->id, 'quantity' => 8]);
        $tooHigh = $this->product($team, 'Deporte caro', '250.00');

        $this->get(route('home', ['ai_query' => 'deporte entre 100 y 200 Bs']))->assertInertia(fn (Assert $page) => $page
            ->where('products.total', 1)->where('products.data.0.id', $mixed->id)->where('products.data.0.price_from', '150.00')->where('products.data.0.available', false));
        $this->get(route('home', ['ai_query' => 'deporte entre 100 y 200 Bs', 'available' => 1, 'branch' => $branch->id]))
            ->assertInertia(fn (Assert $page) => $page->where('products.total', 0));
        Stock::factory()->create(['team_id' => $team->id, 'branch_id' => $branch->id, 'product_variant_id' => $inRange->id, 'quantity' => 1]);
        $this->get(route('home', ['ai_query' => 'deporte entre 100 y 200 Bs', 'available' => 1, 'branch' => $branch->id]))
            ->assertInertia(fn (Assert $page) => $page->where('products.total', 1));
    }

    public function test_inclusive_budget_only_search_does_not_need_a_provider_and_zero_is_not_ignored(): void
    {
        $team = $this->store();
        $free = $this->product($team, 'Gratis', '0.00');
        $equal = $this->product($team, 'Exacto', '200.00');
        $this->product($team, 'Fuera', '200.01');
        Embeddings::fake()->preventStrayEmbeddings();

        $this->get(route('home', ['ai_query' => 'hasta 200 Bs']))->assertInertia(fn (Assert $page) => $page->where('products.total', 2));
        $this->get(route('home', ['ai_query' => 'hasta 0 Bs']))->assertInertia(fn (Assert $page) => $page->where('products.total', 1)->where('products.data.0.id', $free->id));
        $this->get(route('home', ['ai_query' => 'mayor a 200 y menor a 100 Bs']))->assertInertia(fn (Assert $page) => $page->where('products.total', 0));
        Embeddings::assertNothingGenerated();
    }

    public function test_price_is_filtered_before_limiting_semantic_candidates(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('La búsqueda vectorial se verifica con PostgreSQL.');
        }
        $team = $this->store();
        Product::factory()->for($team)->has(ProductVariant::factory()->state(['price' => '250.00']), 'variants')->count(201)->create();
        $cheap = $this->product($team, 'Balón', '100.00');
        config(['semantic-search.enabled' => true, 'semantic-search.provider' => 'openai', 'semantic-search.model' => 'test-embedding', 'semantic-search.dimensions' => 3]);
        Embeddings::fake([[[1.0, 0.0, 0.0]]])->preventStrayEmbeddings();
        $team->products()->update(['search_embedding' => json_encode([1.0, 0.0, 0.0]), 'search_embedding_profile' => app(GenerateSearchEmbedding::class)->profile()]);
        $cheap->forceFill(['search_embedding' => [0.8, 0.6, 0.0]])->saveQuietly();

        $result = app(SearchCatalogSemantically::class)->handle($team, 'deporte', 0, 0, false, null, CatalogPriceFilter::fromQuery('menos de 200 Bs'));

        $this->assertSame([$cheap->id], $result['ids']);
    }

    private function store(): Team
    {
        config(['semantic-search.enabled' => false]);
        $team = Team::factory()->create();
        config(['storefront.team_id' => $team->id]);

        return $team;
    }

    private function product(Team $team, string $name, string $price): Product
    {
        $product = Product::factory()->for($team)->create(['name' => $name]);
        ProductVariant::factory()->for($product)->create(['name' => 'A primera', 'price' => $price]);

        return $product;
    }
}
