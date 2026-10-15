<?php

namespace Tests\Feature;

use App\Enums\TeamRole;
use App\Models\ProductVariant;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeding_creates_a_verified_owner_and_catalog_for_their_team(): void
    {
        $this->seed();

        $user = User::query()->where('email', 'admin@test.com')->sole();
        $team = Team::query()->where('slug', 'tienda-demo')->sole();
        $this->assertTrue(Hash::check('password', $user->password));
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertSame(TeamRole::Owner, $user->teamRole($team));
        $this->assertSame($team->id, $user->current_team_id);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('teams', 1);
        $this->assertSame(6, $team->products()->count());
        $this->assertDatabaseCount('product_variants', 18);
        $this->assertSame(18, ProductVariant::query()->where('team_id', $team->id)->whereHas('product', fn ($query) => $query->where('team_id', $team->id))->count());
        $this->assertDatabaseHas('product_variants', ['sku' => 'ZAP-NEG-42', 'price' => '349.90', 'is_active' => true]);
    }

    public function test_repeating_seeds_preserves_existing_records_passwords_and_prices(): void
    {
        $this->seed();
        $user = User::query()->where('email', 'admin@test.com')->sole();
        $user->update(['password' => 'changed-password']);
        $passwordHash = $user->fresh()->password;
        $variant = ProductVariant::query()->where('sku', 'ZAP-NEG-42')->sole();
        $variant->update(['price' => '399.90', 'is_active' => false]);

        $this->seed();

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('teams', 1);
        $this->assertDatabaseCount('team_members', 1);
        $this->assertDatabaseCount('products', 6);
        $this->assertDatabaseCount('product_variants', 18);
        $this->assertSame($passwordHash, $user->fresh()->password);
        $this->assertDatabaseHas('product_variants', ['id' => $variant->id, 'price' => '399.90', 'is_active' => false]);
    }
}
