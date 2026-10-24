<?php

namespace App\Actions;

use App\Jobs\IndexProductEmbedding;
use App\Models\Product;

class InvalidateProductEmbedding
{
    public function __construct(private GenerateSearchEmbedding $embeddings) {}

    public function handle(int $productId): void
    {
        Product::query()->whereKey($productId)->increment('search_version', 1, [
            'search_embedding' => null, 'search_embedding_profile' => null, 'search_content_hash' => null, 'search_indexed_at' => null,
        ]);
        if ($this->embeddings->ready()) {
            IndexProductEmbedding::dispatch($productId)->onConnection(config('semantic-search.queue_connection'))->afterCommit();
        }
    }
}
