<?php

use App\Ai\AiResponse;
use App\Enums\RunStatus;
use App\Enums\StepStatus;
use Tests\Support\FakeTool;

beforeEach(fn () => actingInWorkspace(workspace()));

function riskyTool(): FakeTool
{
    $tool = new FakeTool('risky', needsApproval: true);
    useTool($tool);

    return $tool;
}

it('pauses before running a tool that needs approval', function () {
    $tool = riskyTool();
    $fake = fakeAi(AiResponse::withToolCall('risky'));

    $run = runner()->start(newRun(newAgent(['risky'])));

    expect($run->status)->toBe(RunStatus::WaitingApproval)
        ->and($run->steps()->first()->status)->toBe(StepStatus::WaitingApproval)
        ->and($tool->executions)->toBe(0);
    $fake->assertAllConsumed();
});

it('runs the tool once a human approves, then finishes the run', function () {
    $tool = riskyTool();
    $fake = fakeAi(AiResponse::withToolCall('risky'), AiResponse::withText('All done.'));
    $run = runner()->start(newRun(newAgent(['risky'])));

    $run = runner()->approve($run->steps()->first());

    expect($run->status)->toBe(RunStatus::Completed)
        ->and($run->output)->toBe('All done.')
        ->and($tool->executions)->toBe(1);
    $fake->assertAllConsumed();
});

it('tells the model when a human rejects the action', function () {
    $tool = riskyTool();
    $fake = fakeAi(AiResponse::withToolCall('risky'), AiResponse::withText('Understood, I will not do it.'));
    $run = runner()->start(newRun(newAgent(['risky'])));

    $run = runner()->reject($run->steps()->first());

    expect($run->status)->toBe(RunStatus::Completed)
        ->and($run->steps()->first()->status)->toBe(StepStatus::Rejected)
        ->and($tool->executions)->toBe(0);

    $messages = $fake->calls[1]['messages'];
    expect(end($messages)['content'])->toContain('Rejected');
    $fake->assertAllConsumed();
});

it('refuses to approve the same step twice', function () {
    $tool = riskyTool();
    fakeAi(AiResponse::withToolCall('risky'), AiResponse::withText('Done.'));
    $run = runner()->start(newRun(newAgent(['risky'])));
    $step = $run->steps()->first();
    runner()->approve($step);

    expect(fn () => runner()->approve($step))->toThrow(LogicException::class);
    expect($tool->executions)->toBe(1); // never run a second time
});

it('refuses a late approval after the run has already failed', function () {
    $tool = riskyTool();
    $fake = fakeAi(AiResponse::withToolCall('risky'));
    $run = runner()->start(newRun(newAgent(['risky'])));
    $step = $run->steps()->first();

    $run->transitionTo(RunStatus::Failed); // pretend the approval window closed

    expect(fn () => runner()->approve($step))->toThrow(LogicException::class);
    expect($tool->executions)->toBe(0);
    $fake->assertAllConsumed();
});