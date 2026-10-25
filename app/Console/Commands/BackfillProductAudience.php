<?php

namespace App\Console\Commands;

use App\Enums\ProductAudience;
use App\Models\Product;
use Database\Seeders\DemoGiftProductSeeder;
use Illuminate\Console\Command;

class BackfillProductAudience extends Command
{
    protected $signature = 'catalog:backfill-audience';

    protected $description = 'Completa el público de productos sin clasificar, conservando los valores existentes';

    public function handle(DemoGiftProductSeeder $seeder): int
    {
        $demo = collect($seeder->catalog())->keyBy('name');
        $updated = 0;
        Product::query()->whereNull('audience')->with(['category', 'team'])->chunkById(50, function ($products) use ($demo, &$updated): void {
            foreach ($products as $product) {
                $data = $product->team->slug === 'tienda-demo' ? $demo->get($product->name) : null;
                $product->update(['audience' => $data['audience'] ?? ProductAudience::forCatalog($product->name, $product->category->name ?? '')->value]);
                $updated++;
            }
        });
        $this->info("Productos clasificados: {$updated}.");

        return self::SUCCESS;
    }
}
