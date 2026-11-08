<?php

use App\Ai\AiResponse;
use App\Enums\RunStatus;
use App\Enums\StepStatus;
use Tests\Support\FakeTool;

beforeEach(fn () => actingInWorkspace(workspace()));

it('runs a tool and feeds the result back to the model', function (){
    $tool = useTool(new FakeTool('lookup', handler: fn (array $args) => 'found: 42'));
    $fake = fakeAi(
        AiResponse::withToolCall('lookup', ['q' => 'answer'], 'call_1'),
        AiResponse::withText('The answer is 42.'),
    );

    $run = runner()->start(newRun(newAgent(['lookup'])));
    $step = $run->steps()->first();

    expect($run->status)->toBe(RunStatus::Completed)
        ->and($tool->executions)->toBe(1)
        ->and($tool->received[0])->toBe(['q' => 'answer'])
        ->and($step->status)->toBe(StepStatus::Completed)
        ->and($step->output)->toBe('found: 42');

        // The second model call must have seen the tool result.
    $messages = $fake->calls[1]['messages'];
    $last = end($messages);

    expect($last['role'])->toBe('tool')
        ->and($last['tool_call_id'])->toBe('call_1')
        ->and($last['content'])->toBe('found: 42');
    $fake->assertAllConsumed();

});

it('only offers the model the tools the agent is allowed to use', function () {
    useTool(new FakeTool('alpha'));
    useTool(new FakeTool('beta'));
    $fake = fakeAi(AiResponse::withText('Nothing to do.'));

    runner()->start(newRun(newAgent(['alpha'])));

    expect(collect($fake->calls[0]['tools'])->pluck('name')->all())->toBe(['alpha']);
});

it('refuses a tool the agent was not given and lets the model recover', function () {
    $secret = useTool(new FakeTool('secret'));
    $fake = fakeAi(
        AiResponse::withToolCall('secret'),
        AiResponse::withText('I could not use that.'),
    );

    $run = runner()->start(newRun(newAgent([])));
    $step = $run->steps()->first();

    expect($run->status)->toBe(RunStatus::Completed)
        ->and($step->status)->toBe(StepStatus::Failed)
        ->and($step->output)->toContain('not available')
        ->and($secret->executions)->toBe(0);
    $fake->assertAllConsumed();
});

it('turns a crashing tool into a failed step instead of a crashed run', function () {
    useTool(new FakeTool('flaky', handler: fn () => throw new RuntimeException('boom')));
    $fake = fakeAi(
        AiResponse::withToolCall('flaky'),
        AiResponse::withText('The tool failed.'),
    );

    $run = runner()->start(newRun(newAgent(['flaky'])));
    $step = $run->steps()->first();

    expect($run->status)->toBe(RunStatus::Completed)
        ->and($step->status)->toBe(StepStatus::Failed)
        ->and($step->output)->toBe('Error: boom');
    $fake->assertAllConsumed();
});