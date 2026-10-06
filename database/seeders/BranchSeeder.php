<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Team;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Team::query()->where('is_personal', false)->each(
            fn (Team $team) => Branch::factory()->for($team)->create(),
        );
    }
}
