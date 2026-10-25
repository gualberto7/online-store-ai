<?php

namespace App\Actions;

use App\Enums\ProductAudience;
use App\Models\Product;
use App\Models\Team;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

class SearchCatalogSemantically
{
    public function __construct(private GenerateSearchEmbedding $embeddings) {}

    public function available(): bool
    {
        return $this->embeddings->ready() && DB::connection()->getDriverName() === 'pgsql';
    }

    /** @return array{ids: list<int>, fallback: bool} */
    public function handle(Team $team, string $search, int $categoryId, int $branchId, bool $availableOnly, ?ProductAudience $audience = null): array
    {
        if (! $this->available()) {
            return ['ids' => [], 'fallback' => true];
        }
        $query = Product::query()->where('team_id', $team->id)->publiclyVisible()
            ->when($audience, fn ($query) => $query->whereIn('audience', array_unique([$audience->value, ProductAudience::Unisex->value])))
            ->whereNotNull('search_embedding')->where('search_embedding_profile', $this->embeddings->profile())
            ->when($categoryId, fn ($query) => $query->where('category_id', $categoryId))
            ->when($availableOnly, fn ($query) => $query->whereHas('variants', fn ($query) => $query->where('is_active', true)
                ->whereHas('stocks', fn ($query) => $query->where('quantity', '>', 0)
                    ->whereHas('branch', fn ($query) => $query->where('is_active', true))
                    ->when($branchId, fn ($query) => $query->where('branch_id', $branchId)))));
        if (! (clone $query)->exists()) {
            return ['ids' => [], 'fallback' => true];
        }
        $limiter = 'semantic-search:'.hash('sha256', $team->id.'|'.request()->ip());
        if (RateLimiter::tooManyAttempts($limiter, 10)) {
            return ['ids' => [], 'fallback' => true];
        }
        RateLimiter::hit($limiter, 60);
        try {
            $key = 'semantic-query:'.hash('sha256', $team->id.'|'.$this->embeddings->profile().'|'.$search);
            $vector = Cache::remember($key, 600, fn () => $this->embeddings->handle($search));
            $ids = $query->whereVectorSimilarTo('search_embedding', $vector, minSimilarity: (float) config('semantic-search.min_similarity'))
                ->limit(200)->get(['id'])->map(fn (Product $product): int => $product->id)->all();

            return ['ids' => array_values($ids), 'fallback' => false];
        } catch (Throwable $exception) {
            report($exception);

            return ['ids' => [], 'fallback' => true];
        }
    }
}
