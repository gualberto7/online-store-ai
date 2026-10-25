<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Team;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class DemoGiftProductSeeder extends Seeder
{
    public function run(): void
    {
        $this->import(Team::query()->where('slug', 'tienda-demo')->where('is_personal', false)->firstOrFail());
    }

    /** @return array<int, Product> */
    public function import(Team $team): array
    {
        return DB::transaction(function () use ($team): array {
            $products = [];
            foreach ($this->catalog() as $data) {
                $category = $team->categories()->firstOrCreate(['name' => $data['category']], ['is_active' => true]);
                $existing = ProductVariant::query()->where('team_id', $team->id)->where('sku', $data['variants'][0]['sku'])->with('product')->first();
                $product = $existing !== null ? $existing->product : $team->products()->firstOrCreate(['name' => $data['name']], [
                    'audience' => $data['audience'], 'description' => $data['description'], 'category_id' => $category->id, 'is_active' => true,
                ]);
                foreach ($data['variants'] as $variantData) {
                    $variant = $product->variants()->firstOrNew(['sku' => $variantData['sku']]);
                    if (! $variant->exists) {
                        $variant->fill([...$variantData, 'is_active' => true]);
                        $variant->team_id = $team->id;
                        $variant->save();
                    }
                }
                $products[$data['source_id']] = $product;
            }

            return $products;
        });
    }

    /** @return list<array{source_id: int, name: string, category: string, audience: string, description: string, image_url: string, variants: list<array{sku: string, name: string, price: string}>}> */
    public function catalog(): array
    {
        return File::json(__DIR__.'/demo-gift-products.json')['products'];
    }
}
