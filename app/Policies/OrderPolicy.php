<?php

namespace App\Policies;

use App\Enums\TeamRole;
use App\Models\Order;
use App\Models\Team;
use App\Models\User;

class OrderPolicy
{
    public function viewAny(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    public function view(User $user, Order $order): bool
    {
        return $this->viewAny($user, $order->team);
    }

    public function create(User $user, Team $team): bool
    {
        return $user->teamRole($team)?->isAtLeast(TeamRole::Admin) ?? false;
    }

    public function manage(User $user, Order $order): bool
    {
        return $this->create($user, $order->team);
    }
}
