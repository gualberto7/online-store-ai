<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => fake()->city(),
            'address' => fake()->streetAddress(),
            'is_active' => true,
        ];
    }
}
