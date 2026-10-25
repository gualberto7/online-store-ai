<?php

namespace App\Actions;

use App\Models\Product;

class IndexProductEmbedding
{
    public function __construct(private GenerateSearchEmbedding $embeddings) {}

    public function handle(int $productId, bool $force = false): bool
    {
        if (! $this->embeddings->ready()) {
            return false;
        }
        $product = Product::query()->where('team_id', (int) config('storefront.team_id'))->publiclyVisible()
            ->whereHas('team', fn ($query) => $query->where('is_personal', false))
            ->with(['category', 'variants' => fn ($query) => $query->where('is_active', true)->orderBy('id')])->find($productId);
        if ($product === null) {
            return false;
        }
        $text = mb_substr(implode("\n", array_filter([
            'Producto: '.$product->name, 'Público: '.$product->audience->label(), $product->description, $product->category ? 'Categoría: '.$product->category->name : null,
            'Variantes: '.$product->variants->map(fn ($variant) => $variant->name.' ('.$variant->sku.')')->implode('; '),
        ])), 0, 12000);
        $hash = hash('sha256', $text);
        $profile = $this->embeddings->profile();
        if (! $force && $product->search_content_hash === $hash && $product->search_embedding_profile === $profile && $product->search_embedding !== null) {
            return false;
        }
        $embedding = $this->embeddings->handle($text);

        return Product::query()->whereKey($product->id)->where('search_version', $product->search_version)->update([
            'search_embedding' => json_encode($embedding, JSON_THROW_ON_ERROR), 'search_content_hash' => $hash,
            'search_embedding_profile' => $profile, 'search_indexed_at' => now(),
        ]) === 1;
    }
}
