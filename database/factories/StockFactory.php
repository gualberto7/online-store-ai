<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\ProductVariant;
use App\Models\Stock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Stock>
 */
class StockFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_variant_id' => ProductVariant::factory(),
            'team_id' => fn (array $attributes) => ProductVariant::query()->whereKey($attributes['product_variant_id'])->sole()->team_id,
            'branch_id' => fn (array $attributes) => Branch::factory()->create(['team_id' => $attributes['team_id']])->id,
            'quantity' => 0,
        ];
    }
}
