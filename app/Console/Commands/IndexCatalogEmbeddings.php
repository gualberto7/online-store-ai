<?php

namespace App\Console\Commands;

use App\Actions\GenerateSearchEmbedding;
use App\Actions\IndexProductEmbedding;
use App\Models\Product;
use App\Models\Team;
use Illuminate\Console\Command;

class IndexCatalogEmbeddings extends Command
{
    protected $signature = 'catalog:index-embeddings {--force : Regenerar también los embeddings vigentes}';

    protected $description = 'Genera embeddings para el catálogo público configurado';

    public function handle(GenerateSearchEmbedding $embeddings, IndexProductEmbedding $index): int
    {
        if (! $embeddings->ready()) {
            $this->error('Configura SEMANTIC_SEARCH_ENABLED, PROVIDER, MODEL y la credencial del proveedor.');

            return self::FAILURE;
        }
        $team = Team::query()->whereKey((int) config('storefront.team_id'))->where('is_personal', false)->first();
        if ($team === null) {
            $this->error('No hay una empresa pública configurada.');

            return self::FAILURE;
        }
        $updated = 0;
        Product::query()->where('team_id', $team->id)->publiclyVisible()->select('id')->chunkById(50, function ($products) use ($index, &$updated): void {
            foreach ($products as $product) {
                if ($index->handle($product->id, (bool) $this->option('force'))) {
                    $updated++;
                }
            }
        });
        $this->info("Productos indexados: {$updated}.");

        return self::SUCCESS;
    }
}
