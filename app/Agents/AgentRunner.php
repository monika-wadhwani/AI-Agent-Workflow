<?php

namespace App\Agents;

use App\Contracts\AiProvider;
use App\Enums\RunStatus;
use App\Enums\StepStatus;
use App\Exceptions\ApprovalExpired;
use App\Models\Run;
use App\Models\RunStep;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LogicException;
use Throwable;

/**
 * Drives one run: ask the model, run its tool calls, feed results back,
 * and stop for human approval when a tool requires it.
 *
 * Known limitation: if a model returns several tool calls in one turn and an
 * earlier one needs approval, the later ones are dropped and the model re-asks.
 */
class AgentRunner
{
    public function __construct(
        private AiProvider $ai,
        private ToolRegistry $tools,
    ) {}

    public function start(Run $run): Run
    {
        $run->transitionTo(RunStatus::Running);

        return $this->execute($run);
    }

    /** Run the loop for a run that is already Running (a queue job claims it first). */
    public function execute(Run $run): Run
    {
        return $this->loop($run);
    }

    public function approve(RunStep $step): Run
    {
        return $this->resolve($step, approved: true);
    }

    public function reject(RunStep $step): Run
    {
        return $this->resolve($step, approved: false);
    }

    /**
     * If this step has been waiting past its deadline, expire it and fail the run.
     * Returns true when it expired just now. Safe to call for any step, any time.
     */
    public function expireIfDue(RunStep $step): bool
    {
        return DB::transaction(function () use ($step) {
            $locked = RunStep::lockForUpdate()->find($step->id);

            if ($locked === null
                || $locked->status !== StepStatus::WaitingApproval
                || $locked->expires_at === null
                || $locked->expires_at->isFuture()) {
                return false;
            }

            $locked->update([
                'status' => StepStatus::Expired,
                'output' => 'Approval expired before anyone answered.',
            ]);

            $run = $locked->run;

            if (! $run->status->isFinished()) {
                $run->error = 'Approval expired: nobody answered in time.';
                $run->transitionTo(RunStatus::Failed);
            }

            return true;
        });
    }

    private function resolve(RunStep $step, bool $approved): Run
    {
        if ($this->expireIfDue($step)) {
            $this->logRefusal($step, $approved, 'expired');

            throw new ApprovalExpired('This approval expired before anyone answered.');
        }

        try {
            $step = $this->claim($step, $approved);
        } catch (LogicException $e) {
            $this->logRefusal($step, $approved, 'not_waiting');

            throw $e;
        }

        $run = $step->run;

        if ($approved) {
            $this->runTool($step);
        }

        $run->transitionTo(RunStatus::Running);

        return $this->loop($run);
    }

    /** Lock the step, check it is still waiting, and record the decision so only one caller can win. */
    private function claim(RunStep $step, bool $approved): RunStep
    {
        return DB::transaction(function () use ($step, $approved) {
            $locked = RunStep::lockForUpdate()->find($step->id);
            $run = $locked?->run;

            if ($locked === null
                || $locked->status !== StepStatus::WaitingApproval
                || $run->status !== RunStatus::WaitingApproval) {
                throw new LogicException('This step is not waiting for approval.');
            }

            $locked->update($approved
                ? ['status' => StepStatus::Pending]
                : ['status' => StepStatus::Rejected, 'output' => 'Rejected by a human reviewer.']);

            return $locked;
        });
    }

    private function logRefusal(RunStep $step, bool $approved, string $reason): void
    {
        Log::warning('Approval decision refused', [
            'step_id' => $step->id,
            'run_id' => $step->run_id,
            'workspace_id' => $step->workspace_id,
            'decision' => $approved ? 'approve' : 'reject',
            'reason' => $reason,
        ]);
    }

    private function loop(Run $run): Run
    {
        $agent = $run->agent;

        try {
            while ($run->steps()->count() < $agent->max_steps) {
                $response = $this->ai->complete(
                    $this->messages($run),
                    $this->tools->definitionsFor($agent->tools),
                    $agent->instructions,
                );

                if (! $response->hasToolCalls()) {
                    $run->output = $response->text;
                    $run->transitionTo(RunStatus::Completed);

                    return $run;
                }

                foreach ($response->toolCalls as $call) {
                    $tool = in_array($call->name, $agent->tools, true) ? $this->tools->get($call->name) : null;

                    $status = match (true) {
                        $tool === null => StepStatus::Failed,
                        $tool->requiresApproval() => StepStatus::WaitingApproval,
                        default => StepStatus::Pending,
                    };

                    $step = $run->steps()->create([
                        'position' => $run->steps()->count() + 1,
                        'tool_call_id' => $call->id,
                        'tool_name' => $call->name,
                        'input' => $call->arguments,
                        'status' => $status,
                        'output' => $tool === null ? "Tool [{$call->name}] is not available to this agent." : null,
                        'expires_at' => $status === StepStatus::WaitingApproval
                            ? now()->addMinutes((int) config('agents.approval_ttl_minutes'))
                            : null,
                    ]);

                    if ($status === StepStatus::WaitingApproval) {
                        $run->transitionTo(RunStatus::WaitingApproval);

                        return $run;
                    }

                    if ($status === StepStatus::Pending) {
                        $this->runTool($step);
                    }
                }
            }

            $run->error = "Step limit of {$agent->max_steps} reached.";
            $run->transitionTo(RunStatus::Failed);
        } catch (Throwable $e) {
            if (! $run->status->isFinished()) {
                $run->error = $e->getMessage();
                $run->transitionTo(RunStatus::Failed);
            }
        }

        return $run;
    }

    /** Run one tool; failures become step output so the model can recover. */
    private function runTool(RunStep $step): void
    {
        $tool = $this->tools->get($step->tool_name);

        if ($tool === null) {
            $step->update(['status' => StepStatus::Failed, 'output' => "Tool [{$step->tool_name}] is not available."]);

            return;
        }

        try {
            $step->update(['status' => StepStatus::Completed, 'output' => $tool->execute($step->input ?? [])]);
        } catch (Throwable $e) {
            $step->update(['status' => StepStatus::Failed, 'output' => 'Error: '.$e->getMessage()]);
        }
    }

    /** Rebuild the provider-neutral conversation from the saved steps. */
    private function messages(Run $run): array
    {
        $messages = [['role' => 'user', 'content' => $run->input]];

        foreach ($run->steps()->get() as $step) {
            $messages[] = [
                'role' => 'assistant',
                'content' => null,
                'tool_calls' => [['id' => $step->tool_call_id, 'name' => $step->tool_name, 'arguments' => $step->input]],
            ];
            $messages[] = ['role' => 'tool', 'tool_call_id' => $step->tool_call_id, 'content' => $step->output ?? ''];
        }

        return $messages;
    }
}
