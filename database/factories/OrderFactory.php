<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Branch;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'team_id' => fn (array $attributes) => Branch::query()->whereKey($attributes['branch_id'])->sole()->team_id,
            'status' => OrderStatus::Draft,
            'total_cents' => 0,
        ];
    }
}
