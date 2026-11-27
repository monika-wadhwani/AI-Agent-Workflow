<?php

use App\Agents\AgentRunner;
use App\Enums\RunStatus;
use App\Enums\StepStatus;
use App\Exceptions\ApprovalExpired;
use App\Models\Run;
use Illuminate\Support\Facades\Log;
use Tests\Support\FakeTool;

beforeEach(function () {
    $this->workspace = workspace('Acme');
    actingInWorkspace($this->workspace);
});

it('gives approvals a 24 hour window by default', function () {
    expect(config('agents.approval_ttl_minutes'))->toBe(1440);
});

it('stamps an expiry time on a step when it pauses for approval', function () {
    config(['agents.approval_ttl_minutes' => 60]);

    $step = pausedStep(new FakeTool('risky', needsApproval: true));

    expect($step->expires_at)->not->toBeNull()
        ->and($step->expires_at->between(now()->addMinutes(59), now()->addMinutes(61)))->toBeTrue();
});

it('expires a waiting approval after the deadline and fails the run', function () {
    $tool = new FakeTool('risky', needsApproval: true);
    $step = pausedStep($tool);

    $this->travel(25)->hours();

    expect(app(AgentRunner::class)->expireIfDue($step))->toBeTrue();

    $step->refresh();
    $run = Run::find($step->run_id);
    expect($step->status)->toBe(StepStatus::Expired)
        ->and($run->status)->toBe(RunStatus::Failed)
        ->and($run->error)->toContain('expired')
        ->and($tool->executions)->toBe(0);
});

it('leaves an approval alone while it is still inside its window', function () {
    $step = pausedStep(new FakeTool('risky', needsApproval: true));

    $this->travel(1)->hours();

    expect(app(AgentRunner::class)->expireIfDue($step))->toBeFalse()
        ->and($step->refresh()->status)->toBe(StepStatus::WaitingApproval);
});

it('refuses a late approval and never runs the tool', function () {
    $tool = new FakeTool('risky', needsApproval: true);
    $step = pausedStep($tool);

    $this->travel(25)->hours();

    expect(fn () => runner()->approve($step))->toThrow(ApprovalExpired::class);
    expect($tool->executions)->toBe(0)
        ->and(Run::find($step->run_id)->status)->toBe(RunStatus::Failed);
});

it('logs a late approval instead of dropping it silently', function () {
    Log::spy();
    $step = pausedStep(new FakeTool('risky', needsApproval: true));

    $this->travel(25)->hours();

    expect(fn () => runner()->approve($step))->toThrow(ApprovalExpired::class);

    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(fn (string $message, array $context) => $context['step_id'] === $step->id
            && $context['reason'] === 'expired'
            && $context['decision'] === 'approve');
});

it('logs a second decision on a step that was already decided', function () {
    Log::spy();
    $tool = new FakeTool('risky', needsApproval: true);
    $step = pausedStep($tool);
    runner()->approve($step);

    expect(fn () => runner()->approve($step))->toThrow(LogicException::class);

    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(fn (string $message, array $context) => $context['reason'] === 'not_waiting');
    expect($tool->executions)->toBe(1);
});
