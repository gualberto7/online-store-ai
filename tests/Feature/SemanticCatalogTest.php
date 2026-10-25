<?php

namespace Tests\Feature;

use App\Actions\GenerateSearchEmbedding;
use App\Actions\IndexProductEmbedding;
use App\Jobs\IndexProductEmbedding as IndexEmbeddingJob;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Stock;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Prompts\EmbeddingsPrompt;
use PHPUnit\Framework\Attributes\TestWith;
use RuntimeException;
use Tests\TestCase;

class SemanticCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_and_indexing_remain_off_without_a_provider(): void
    {
        [, $product] = $this->catalog();
        Embeddings::fake()->preventStrayEmbeddings();
        config(['semantic-search.enabled' => false]);

        $this->get(route('home', ['ai_query' => $product->name]))->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 1)->where('semanticSearch.available', false)->where('semanticSearch.fallback', true));
        $this->artisan('catalog:index-embeddings')->assertFailed();
        Embeddings::assertNothingGenerated();
    }

    public function test_normal_search_never_generates_embeddings_even_with_the_old_semantic_flag(): void
    {
        [, $product] = $this->catalog();
        $this->enable();
        Embeddings::fake()->preventStrayEmbeddings();

        $this->get(route('home', ['q' => 'Mochila', 'semantic' => 1]))->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 1)->where('products.data.0.id', $product->id)
            ->where('filters.q', 'Mochila')->where('filters.ai_query', '')->where('semanticSearch.fallback', false));

        Embeddings::assertNothingGenerated();
    }

    public function test_ai_descriptions_accept_more_text_and_remain_separate_from_normal_search(): void
    {
        $this->catalog();
        config(['semantic-search.enabled' => false]);
        $description = str_repeat('Quiero un regalo para viajar. ', 10);

        $this->get(route('home', ['q' => 'Mochila', 'ai_query' => $description]))->assertInertia(fn (Assert $page) => $page
            ->where('filters.q', 'Mochila')->where('filters.ai_query', trim($description))
            ->where('filters.sort', 'relevance')->where('semanticSearch.fallback', true));
    }

    #[TestWith(['ai_query', 1001])]
    #[TestWith(['q', 121])]
    public function test_each_search_field_enforces_its_own_length_limit(string $field, int $length): void
    {
        $this->catalog();

        $this->get(route('home', [$field => str_repeat('x', $length)]))->assertSessionHasErrors($field);
    }

    public function test_pagination_preserves_the_ai_description_and_other_filters(): void
    {
        [$team] = $this->catalog();
        Product::factory()->for($team)->has(ProductVariant::factory(), 'variants')->count(12)->create(['name' => 'Mochila de regalo']);
        config(['semantic-search.enabled' => false]);

        $this->get(route('home', ['ai_query' => 'Mochila']))->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 12)->where('products.total', 13)
            ->where('products.next_page_url', fn (string $url): bool => str_contains($url, 'ai_query=Mochila')));
    }

    public function test_index_includes_catalog_text_but_excludes_prices_stock_and_inactive_variants(): void
    {
        [$team, $product] = $this->catalog();
        $category = Category::factory()->for($team)->create(['name' => 'Equipaje']);
        $product->update(['category_id' => $category->id, 'description' => 'Para viajes de fin de semana']);
        ProductVariant::factory()->for($product)->create(['name' => 'VARIANTE OCULTA', 'is_active' => false]);
        $this->enable();
        Embeddings::fake([[[1.0, 0.0, 0.0]]])->preventStrayEmbeddings();

        $this->assertTrue(app(IndexProductEmbedding::class)->handle($product->id));

        $this->assertSame([1.0, 0.0, 0.0], $product->fresh()->search_embedding);
        $this->assertNotNull($product->fresh()->getAttribute('search_indexed_at'));
        Embeddings::assertGenerated(fn (EmbeddingsPrompt $prompt) => $prompt->contains('Equipaje') && $prompt->contains('Para viajes')
            && $prompt->contains('Negro / grande') && ! $prompt->contains('VARIANTE OCULTA') && ! $prompt->contains('100.00'));
    }

    public function test_unchanged_documents_are_not_embedded_again_but_model_changes_trigger_reindexing(): void
    {
        [, $product] = $this->catalog();
        $this->enable();
        Embeddings::fake([[[1.0, 0.0, 0.0]], [[0.0, 1.0, 0.0]]])->preventStrayEmbeddings();
        $index = app(IndexProductEmbedding::class);
        $this->assertTrue($index->handle($product->id));

        $this->assertFalse($index->handle($product->id));
        config(['semantic-search.model' => 'different-test-model']);
        $this->assertTrue($index->handle($product->id));

        $this->assertSame([0.0, 1.0, 0.0], $product->fresh()->search_embedding);
    }

    #[TestWith(['product'])]
    #[TestWith(['variant'])]
    #[TestWith(['category'])]
    public function test_catalog_edits_invalidate_old_embeddings_and_queue_an_update(string $changed): void
    {
        [$team, $product] = $this->catalog();
        $category = Category::factory()->for($team)->create();
        $product->update(['category_id' => $category->id]);
        $this->enable();
        Embeddings::fake([[[1.0, 0.0, 0.0]]])->preventStrayEmbeddings();
        app(IndexProductEmbedding::class)->handle($product->id);
        Queue::fake([IndexEmbeddingJob::class]);

        match ($changed) {
            'product' => $product->update(['description' => 'Nueva descripción']),
            'variant' => $product->variants()->sole()->update(['name' => 'Azul / grande']),
            'category' => $category->update(['name' => 'Nueva categoría']),
        };

        $this->assertNull($product->fresh()->search_embedding);
        Queue::assertPushed(IndexEmbeddingJob::class, fn ($job) => $job->productId === $product->id);
    }

    public function test_price_and_stock_updates_do_not_regenerate_embeddings(): void
    {
        [, $product] = $this->catalog();
        $this->enable();
        Embeddings::fake([[[1.0, 0.0, 0.0]]])->preventStrayEmbeddings();
        app(IndexProductEmbedding::class)->handle($product->id);
        Queue::fake([IndexEmbeddingJob::class]);

        $product->variants()->sole()->update(['price' => '25.00']);
        Stock::factory()->create(['product_variant_id' => $product->variants()->sole()->id, 'quantity' => 5]);

        $this->assertNotNull($product->fresh()->search_embedding);
        Queue::assertNotPushed(IndexEmbeddingJob::class);
    }

    public function test_generation_cannot_overwrite_a_product_edited_during_the_api_call(): void
    {
        [, $product] = $this->catalog();
        $this->enable();
        Queue::fake([IndexEmbeddingJob::class]);
        Embeddings::fake(function () use ($product): array {
            $product->update(['description' => 'Cambio mientras se indexaba']);

            return [[1.0, 0.0, 0.0]];
        });

        $this->assertFalse(app(IndexProductEmbedding::class)->handle($product->id));
        $this->assertNull($product->fresh()->search_embedding);
    }

    public function test_command_indexes_only_the_public_configured_business(): void
    {
        [$team, $product] = $this->catalog();
        $foreign = Product::factory()->has(ProductVariant::factory(), 'variants')->create();
        $inactive = Product::factory()->for($team)->has(ProductVariant::factory(), 'variants')->create(['is_active' => false]);
        $this->enable();
        Embeddings::fake([[[1.0, 0.0, 0.0]]])->preventStrayEmbeddings();

        $this->artisan('catalog:index-embeddings')->expectsOutput('Productos indexados: 1.')->assertSuccessful();

        $this->assertNotNull($product->fresh()->search_embedding);
        $this->assertNull($foreign->fresh()->search_embedding);
        $this->assertNull($inactive->fresh()->search_embedding);
    }

    public function test_embeddings_and_internal_index_metadata_are_not_serialized(): void
    {
        [, $product] = $this->catalog();
        $this->enable();
        Embeddings::fake([[[1.0, 0.0, 0.0]]])->preventStrayEmbeddings();
        app(IndexProductEmbedding::class)->handle($product->id);

        $this->assertArrayNotHasKey('search_embedding', $product->fresh()->toArray());
        $this->assertArrayNotHasKey('search_embedding_profile', $product->fresh()->toArray());
        $this->get(route('storefront.show', $product))->assertInertia(fn (Assert $page) => $page->missing('product.search_embedding'));
    }

    #[TestWith([[1.0, 0.0]])]
    #[TestWith([[0.0, 0.0, 0.0]])]
    public function test_invalid_provider_vectors_are_rejected(array $vector): void
    {
        $this->enable();
        Embeddings::fake([[$vector]])->preventStrayEmbeddings();
        $this->expectException(RuntimeException::class);

        app(GenerateSearchEmbedding::class)->handle('Prueba');
    }

    public function test_vector_search_finds_meaning_matches_and_respects_live_category_branch_and_stock(): void
    {
        $this->requirePostgres();
        [$team, $product] = $this->catalog();
        $category = Category::factory()->for($team)->create();
        $product->update(['category_id' => $category->id]);
        $branch = Branch::factory()->for($team)->create();
        $stock = Stock::factory()->create(['branch_id' => $branch->id, 'team_id' => $team->id, 'product_variant_id' => $product->variants()->sole()->id, 'quantity' => 1]);
        $this->enable();
        Embeddings::fake([[[1.0, 0.0, 0.0]], [[1.0, 0.0, 0.0]]])->preventStrayEmbeddings();
        app(IndexProductEmbedding::class)->handle($product->id);
        $filters = ['ai_query' => 'algo para viajar', 'sort' => 'relevance', 'branch' => $branch->id, 'available' => 1, 'category' => $category->id];
        $product->variants()->sole()->update(['price' => '9.99']);

        $this->get(route('home', $filters))->assertInertia(fn (Assert $page) => $page->has('products.data', 1)
            ->where('products.data.0.id', $product->id)->where('products.data.0.price_from', '9.99')->where('semanticSearch.fallback', false));
        $stock->update(['quantity' => 0]);
        $this->get(route('home', $filters))->assertInertia(fn (Assert $page) => $page->has('products.data', 0));
    }

    public function test_hybrid_search_keeps_exact_sku_matches_and_excludes_foreign_inactive_or_old_model_vectors(): void
    {
        $this->requirePostgres();
        [$team, $product] = $this->catalog();
        $exact = Product::factory()->for($team)->create(['name' => 'Exacto']);
        ProductVariant::factory()->for($exact)->create(['sku' => 'CODIGO-EXACTO']);
        $foreign = Product::factory()->has(ProductVariant::factory(), 'variants')->create();
        $this->enable();
        Embeddings::fake([[[1.0, 0.0, 0.0]], [[1.0, 0.0, 0.0]]])->preventStrayEmbeddings();
        app(IndexProductEmbedding::class)->handle($product->id);
        $foreign->forceFill(['search_embedding' => [1.0, 0.0, 0.0], 'search_embedding_profile' => app(GenerateSearchEmbedding::class)->profile()])->saveQuietly();

        $this->get(route('home', ['ai_query' => 'CODIGO-EXACTO', 'sort' => 'relevance']))
            ->assertInertia(fn (Assert $page) => $page->has('products.data', 2)->where('products.data.0.id', $exact->id)->where('products.data.1.id', $product->id));
        $this->get(route('home', ['q' => 'Exacto', 'ai_query' => 'CODIGO-EXACTO']))
            ->assertInertia(fn (Assert $page) => $page->has('products.data', 1)->where('products.data.0.id', $exact->id)
                ->where('filters.q', 'Exacto')->where('filters.ai_query', 'CODIGO-EXACTO'));
        config(['semantic-search.model' => 'new-model']);
        $this->get(route('home', ['ai_query' => 'sin coincidencia']))
            ->assertInertia(fn (Assert $page) => $page->has('products.data', 0)->where('semanticSearch.fallback', true));
    }

    public function test_provider_outage_falls_back_to_text_search(): void
    {
        $this->requirePostgres();
        [, $product] = $this->catalog();
        $this->enable();
        Embeddings::fake([[[1.0, 0.0, 0.0]]])->preventStrayEmbeddings();
        app(IndexProductEmbedding::class)->handle($product->id);
        Embeddings::fake(fn () => throw new RuntimeException('Proveedor no disponible'));
        Exceptions::fake();

        $this->get(route('home', ['ai_query' => 'Mochila']))->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 1)->where('semanticSearch.fallback', true));
        Exceptions::assertReported(RuntimeException::class);
    }

    private function enable(): void
    {
        config(['semantic-search.enabled' => true, 'semantic-search.provider' => 'openai', 'semantic-search.model' => 'test-embedding', 'semantic-search.dimensions' => 3]);
    }

    private function requirePostgres(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('La búsqueda vectorial se verifica en PostgreSQL con pgvector.');
        }
    }

    /** @return array{Team, Product} */
    private function catalog(): array
    {
        $team = Team::factory()->create();
        config(['storefront.team_id' => $team->id]);
        $product = Product::factory()->for($team)->create(['name' => 'Mochila', 'description' => 'Bolso resistente']);
        ProductVariant::factory()->for($product)->create(['name' => 'Negro / grande']);

        return [$team, $product];
    }
}
