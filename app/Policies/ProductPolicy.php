<?php

namespace App\Policies;

use App\Enums\TeamRole;
use App\Models\Product;
use App\Models\Team;
use App\Models\User;

class ProductPolicy
{
    public function viewAny(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    public function view(User $user, Product $product): bool
    {
        return $this->viewAny($user, $product->team);
    }

    public function create(User $user, Team $team): bool
    {
        return $user->teamRole($team)?->isAtLeast(TeamRole::Admin) ?? false;
    }

    public function update(User $user, Product $product): bool
    {
        return $this->create($user, $product->team);
    }
}
