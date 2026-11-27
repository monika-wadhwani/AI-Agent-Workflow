<?php

namespace App\Jobs;

use App\Agents\AgentRunner;
use App\Enums\RunStatus;
use App\Models\Run;
use App\Models\Workspace;
use App\Support\CurrentWorkspace;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Runs one agent run in the background.
 *
 * It carries ids, not models: a model would be reloaded when the job wakes up,
 * and at that moment no workspace is set, so the tenant guard would hide it.
 */
class ExecuteRun implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /** Never retry by itself: a retry could run a tool twice (the same email sent again). */
    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(
        public readonly int $runId,
        public readonly int $workspaceId,
    ) {}

    public function handle(AgentRunner $runner, CurrentWorkspace $current): void
    {
        $workspace = Workspace::findOrFail($this->workspaceId);

        $current->runAs($workspace, function () use ($runner) {
            $run = $this->claim();

            if ($run === null) {
                return; // another worker took it, or it already finished
            }

            $runner->execute($run);
        });
    }

    /** Called by the queue when the job crashes or times out outside the runner's own error handling. */
    public function failed(Throwable $e): void
    {
        $workspace = Workspace::find($this->workspaceId);

        if ($workspace === null) {
            return;
        }

        app(CurrentWorkspace::class)->runAs($workspace, function () use ($e) {
            $run = Run::find($this->runId);

            if ($run === null || $run->status->isFinished()) {
                return;
            }

            $run->error = 'Job failed: '.$e->getMessage();
            $run->transitionTo(RunStatus::Failed);
        });
    }

    /** Only one worker can move a run from pending to running. */
    private function claim(): ?Run
    {
        return DB::transaction(function () {
            $run = Run::lockForUpdate()->find($this->runId);

            if ($run === null || $run->status !== RunStatus::Pending) {
                return null;
            }

            $run->transitionTo(RunStatus::Running);

            return $run;
        });
    }
}