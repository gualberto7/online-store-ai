<?php

namespace Tests\Feature;

use App\Enums\TeamRole;
use App\Models\Branch;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class BranchTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_must_sign_in(): void
    {
        $team = Team::factory()->create();

        $this->get(route('branches.index', $team))->assertRedirect(route('login'));
    }

    public function test_members_see_only_their_business_branches(): void
    {
        [$user, $team] = $this->member(TeamRole::Member);
        Branch::factory()->for($team)->create(['name' => 'Centro']);
        Branch::factory()->create(['name' => 'Otro negocio']);

        $this->actingAs($user)->get(route('branches.index', $team))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->component('branches/index')
            ->has('branches', 1)->where('branches.0.name', 'Centro')->where('canManage', false));
    }

    #[TestWith(['owner', true])]
    #[TestWith(['admin', true])]
    #[TestWith(['member', false])]
    public function test_branch_permissions_follow_business_roles(string $role, bool $allowed): void
    {
        [$user, $team] = $this->member(TeamRole::from($role));
        $branch = Branch::factory()->for($team)->create();
        $otherTeam = Team::factory()->create();
        $otherBranch = Branch::factory()->for($otherTeam)->create();

        $this->assertTrue($user->can('viewAny', [Branch::class, $team]));
        $this->assertSame($allowed, $user->can('create', [Branch::class, $team]));
        $this->assertSame($allowed, $user->can('update', $branch));
        $this->assertFalse($user->can('viewAny', [Branch::class, $otherTeam]));
        $this->assertFalse($user->can('create', [Branch::class, $otherTeam]));
        $this->assertFalse($user->can('update', $otherBranch));
    }

    public function test_administrators_can_create_branches_without_overriding_the_business(): void
    {
        [$user, $team] = $this->member(TeamRole::Admin);
        $otherTeam = Team::factory()->create();

        $this->actingAs($user)->post(route('branches.store', $team), [
            'name' => 'Centro', 'address' => 'La Paz', 'is_active' => '1', 'team_id' => $otherTeam->id,
        ])->assertRedirect(route('branches.index', $team));

        $this->assertDatabaseHas('branches', [
            'team_id' => $team->id, 'name' => 'Centro', 'address' => 'La Paz', 'is_active' => true,
        ]);
        $this->assertDatabaseCount('branches', 1);
    }

    public function test_administrators_can_edit_and_deactivate_branches(): void
    {
        [$user, $team] = $this->member(TeamRole::Admin);
        $branch = Branch::factory()->for($team)->create();

        $this->actingAs($user)->patch(route('branches.update', [$team, $branch]), [
            'name' => 'Sur', 'address' => 'Santa Cruz', 'is_active' => '0',
        ])->assertRedirect(route('branches.index', $team));

        $this->assertDatabaseHas('branches', [
            'id' => $branch->id, 'team_id' => $team->id,
            'name' => 'Sur', 'address' => 'Santa Cruz', 'is_active' => false,
        ]);
    }

    public function test_branch_forms_can_be_rendered(): void
    {
        [$user, $team] = $this->member(TeamRole::Owner);
        $branch = Branch::factory()->for($team)->create();

        $this->actingAs($user)->get(route('branches.create', $team))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->component('branches/form'));
        $this->get(route('branches.edit', [$team, $branch]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page->component('branches/form')->where('branch.id', $branch->id));
    }

    public function test_members_cannot_manage_branches(): void
    {
        [$user, $team] = $this->member(TeamRole::Member);
        $branch = Branch::factory()->for($team)->create(['name' => 'Centro']);
        $payload = ['name' => 'Sur', 'address' => 'La Paz', 'is_active' => true];

        $this->actingAs($user)->get(route('branches.create', $team))->assertForbidden();
        $this->get(route('branches.edit', [$team, $branch]))->assertForbidden();
        $this->post(route('branches.store', $team), $payload)->assertForbidden();
        $this->patch(route('branches.update', [$team, $branch]), $payload)->assertForbidden();

        $this->assertDatabaseHas('branches', ['id' => $branch->id, 'name' => 'Centro']);
        $this->assertDatabaseCount('branches', 1);
    }

    public function test_users_cannot_access_a_business_they_do_not_belong_to(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();

        $this->actingAs($user)->get(route('branches.index', $team))->assertForbidden();
    }

    public function test_cross_business_branch_identifiers_are_not_found(): void
    {
        [$user, $team] = $this->member(TeamRole::Owner);
        $branch = Branch::factory()->create(['name' => 'Otra empresa']);

        $this->actingAs($user)->get(route('branches.edit', [$team, $branch]))->assertNotFound();
        $this->patch(route('branches.update', [$team, $branch]), [
            'name' => 'Sur', 'address' => 'La Paz', 'is_active' => true,
        ])->assertNotFound();

        $this->assertDatabaseHas('branches', ['id' => $branch->id, 'name' => 'Otra empresa']);
    }

    #[TestWith(['name', ''])]
    #[TestWith(['address', ''])]
    #[TestWith(['is_active', 'invalid'])]
    public function test_invalid_branch_data_is_rejected(string $field, string $value): void
    {
        [$user, $team] = $this->member(TeamRole::Owner);
        $payload = ['name' => 'Centro', 'address' => 'La Paz', 'is_active' => true];
        $payload[$field] = $value;

        $this->actingAs($user)->post(route('branches.store', $team), $payload)->assertSessionHasErrors($field);

        $this->assertDatabaseCount('branches', 0);
    }

    /** @return array{User, Team} */
    private function member(TeamRole $role): array
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($user, ['role' => $role->value]);

        return [$user, $team];
    }
}
