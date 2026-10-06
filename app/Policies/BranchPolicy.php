<?php

namespace App\Policies;

use App\Enums\TeamRole;
use App\Models\Branch;
use App\Models\Team;
use App\Models\User;

class BranchPolicy
{
    public function viewAny(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    public function create(User $user, Team $team): bool
    {
        return $user->teamRole($team)?->isAtLeast(TeamRole::Admin) ?? false;
    }

    public function update(User $user, Branch $branch): bool
    {
        return $this->create($user, $branch->team);
    }
}
