<?php

namespace App\Console\Commands;

use App\Agents\AgentRunner;
use App\Enums\StepStatus;
use App\Models\RunStep;
use App\Models\Workspace;
use App\Support\CurrentWorkspace;
use Illuminate\Console\Command;

class ExpireApprovals extends Command
{
    protected $signature = 'agents:expire-approvals';

    protected $description = 'Fail runs whose human approval was not answered in time';

    public function handle(AgentRunner $runner, CurrentWorkspace $current): int
    {
        $expired = 0;

        // The scheduler has no workspace, so look across all of them,
        // then do the actual work inside each step's own workspace.
        RunStep::withoutGlobalScopes()
            ->where('status', StepStatus::WaitingApproval->value)
            ->where('expires_at', '<', now())
            ->lazyById(100)
            ->each(function (RunStep $step) use ($runner, $current, &$expired) {
                $workspace = Workspace::find($step->workspace_id);

                if ($workspace === null) {
                    return;
                }

                if ($current->runAs($workspace, fn () => $runner->expireIfDue($step))) {
                    $expired++;
                }
            });

        $this->info("Expired {$expired} approval(s).");

        return self::SUCCESS;
    }
}
