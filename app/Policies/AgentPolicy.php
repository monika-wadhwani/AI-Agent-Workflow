<?php

namespace App\Policies;

use App\Enums\WorkspaceRole;
use App\Models\Agent;
use App\Models\User;
use App\Models\Workspace;

class AgentPolicy
{
    public function create(User $user, Workspace $workspace): bool
    {
        return $user->roleIn($workspace) === WorkspaceRole::Owner;
    }

    public function start(User $user, Agent $agent): bool
    {
        return $user->roleIn($agent->workspace) !== null;
    }
}