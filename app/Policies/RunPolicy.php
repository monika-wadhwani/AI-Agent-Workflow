<?php

namespace App\Policies;

use App\Models\Run;
use App\Models\User;

class RunPolicy
{
    public function view(User $user, Run $run): bool
    {
        return $user->roleIn($run->workspace) !== null;
    }
}