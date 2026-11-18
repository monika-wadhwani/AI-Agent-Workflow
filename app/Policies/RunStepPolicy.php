<?php

namespace App\Policies;

use App\Enums\WorkspaceRole;
use App\Models\RunStep;
use App\Models\User;

class RunStepPolicy
{
    /** Approving and rejecting are both the boss's job. */
    public function decide(User $user, RunStep $step): bool
    {
        return $user->roleIn($step->workspace) === WorkspaceRole::Owner;
    }
}