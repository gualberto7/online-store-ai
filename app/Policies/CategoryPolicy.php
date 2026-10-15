<?php

namespace App\Policies;

use App\Enums\TeamRole;
use App\Models\Category;
use App\Models\Team;
use App\Models\User;

class CategoryPolicy
{
    public function viewAny(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    public function create(User $user, Team $team): bool
    {
        return $user->teamRole($team)?->isAtLeast(TeamRole::Admin) ?? false;
    }

    public function update(User $user, Category $category): bool
    {
        return $this->create($user, $category->team);
    }
}
