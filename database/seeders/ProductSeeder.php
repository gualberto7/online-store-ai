<?php

namespace Database\Seeders;

use App\Models\Team;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $team = Team::query()->where('slug', 'tienda-demo')->firstOrFail();

        foreach ($this->catalog() as $data) {
            $product = $team->products()->firstOrCreate(
                ['name' => $data['name']],
                ['description' => $data['description'], 'is_active' => true],
            );

            foreach ($data['variants'] as $variantData) {
                $variant = $product->variants()->firstOrNew(['sku' => $variantData['sku']]);

                if (! $variant->exists) {
                    $variant->fill([...$variantData, 'is_active' => true]);
                    $variant->team_id = $team->id;
                    $variant->save();
                }
            }
        }
    }

    /** @return array<int, array{name: string, description: string, variants: array<int, array{sku: string, name: string, price: string}>}> */
    private function catalog(): array
    {
        return [
            [
                'name' => 'Zapatillas Urbanas',
                'description' => 'Zapatillas ligeras con suela amortiguada para caminar y uso diario.',
                'variants' => [
                    ['sku' => 'ZAP-NEG-40', 'name' => 'Negro / talla 40', 'price' => '349.90'],
                    ['sku' => 'ZAP-NEG-42', 'name' => 'Negro / talla 42', 'price' => '349.90'],
                    ['sku' => 'ZAP-BLA-41', 'name' => 'Blanco / talla 41', 'price' => '369.90'],
                ],
            ],
            [
                'name' => 'Camiseta de Algodón',
                'description' => 'Camiseta de algodón suave y transpirable, ideal para uso diario.',
                'variants' => [
                    ['sku' => 'CAM-BLA-S', 'name' => 'Blanco / S', 'price' => '79.90'],
                    ['sku' => 'CAM-BLA-M', 'name' => 'Blanco / M', 'price' => '79.90'],
                    ['sku' => 'CAM-NEG-L', 'name' => 'Negro / L', 'price' => '79.90'],
                ],
            ],
            [
                'name' => 'Sudadera con Capucha',
                'description' => 'Sudadera abrigada con bolsillo frontal para días frescos.',
                'variants' => [
                    ['sku' => 'SUD-GRI-S', 'name' => 'Gris / S', 'price' => '199.00'],
                    ['sku' => 'SUD-GRI-M', 'name' => 'Gris / M', 'price' => '199.00'],
                    ['sku' => 'SUD-NEG-L', 'name' => 'Negro / L', 'price' => '219.00'],
                ],
            ],
            [
                'name' => 'Mochila Urbana',
                'description' => 'Mochila de 20 litros con compartimento acolchado para portátil de hasta 15 pulgadas.',
                'variants' => [
                    ['sku' => 'MOC-NEG-20', 'name' => 'Negro / 20 litros', 'price' => '249.00'],
                    ['sku' => 'MOC-AZU-20', 'name' => 'Azul / 20 litros', 'price' => '249.00'],
                    ['sku' => 'MOC-GRI-20', 'name' => 'Gris / 20 litros', 'price' => '249.00'],
                ],
            ],
            [
                'name' => 'Botella Térmica',
                'description' => 'Botella de acero inoxidable con aislamiento térmico y tapa hermética.',
                'variants' => [
                    ['sku' => 'BOT-NEG-500', 'name' => 'Negro / 500 ml', 'price' => '89.50'],
                    ['sku' => 'BOT-AZU-750', 'name' => 'Azul / 750 ml', 'price' => '109.50'],
                    ['sku' => 'BOT-PLA-1000', 'name' => 'Plata / 1 litro', 'price' => '129.50'],
                ],
            ],
            [
                'name' => 'Gorra Deportiva',
                'description' => 'Gorra ajustable con tejido ligero y ventilación para actividades al aire libre.',
                'variants' => [
                    ['sku' => 'GOR-NEG-UNI', 'name' => 'Negro / talla única', 'price' => '59.00'],
                    ['sku' => 'GOR-AZU-UNI', 'name' => 'Azul / talla única', 'price' => '59.00'],
                    ['sku' => 'GOR-BLA-UNI', 'name' => 'Blanco / talla única', 'price' => '59.00'],
                ],
            ],
        ];
    }
}
