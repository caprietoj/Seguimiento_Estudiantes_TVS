<?php

namespace App\Policies;

use App\Models\Group;
use App\Models\User;
use App\Support\Scope;

class GroupPolicy
{
    public function view(User $user, Group $group): bool
    {
        return Scope::inScope($user, $group);
    }
}
