<?php

namespace Database\Seeders;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->firstOrCreate(
            ['email' => 'admin@test.com'],
            ['name' => 'Administrador', 'password' => 'password'],
        );

        if ($user->wasRecentlyCreated) {
            $user->markEmailAsVerified();
        }

        $team = Team::query()->firstOrCreate(
            ['slug' => 'tienda-demo'],
            ['name' => 'Tienda Demo', 'is_personal' => false],
        );

        $team->members()->syncWithoutDetaching([
            $user->id => ['role' => TeamRole::Owner->value],
        ]);

        if ($user->current_team_id === null) {
            $user->switchTeam($team);
        }
    }
}
