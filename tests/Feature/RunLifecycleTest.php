<?php

use App\Ai\AiResponse;
use App\Enums\RunStatus;
use App\Events\RunStatusChanged;
use Illuminate\Support\Facades\Event;
use Tests\Support\FakeTool;

beforeEach(fn () => actingInWorkspace(workspace()));

it('completes a run when the model answers without calling tools', function (){
    $fake = fakeAi(AiResponse::withText('All done.'));

    $run = runner()->start(newRun(newAgent()));

    expect($run->status)->toBe(RunStatus::Completed)
        ->and($run->output)->toBe('All done.');
    $fake->assertAllConsumed();
});

it('fails the run and records the error when the provider throws', function () {
    fakeAi(); // no scripted responses, so the fake throws

    $run = runner()->start(newRun(newAgent()));

    expect($run->status)->toBe(RunStatus::Failed)
        ->and($run->error)->toContain('no more scripted responses');
});

it('stops a runaway agent at its step limit', function () {
    $tool = useTool(new FakeTool('looper'));
    $fake = fakeAi(
        AiResponse::withToolCall('looper'),
        AiResponse::withToolCall('looper'),
    );

    $run = runner()->start(newRun(newAgent(['looper'], maxSteps: 2)));

    expect($run->status)->toBe(RunStatus::Failed)
        ->and($run->error)->toContain('Step limit of 2')
        ->and($run->steps()->count())->toBe(2)
        ->and($tool->executions)->toBe(2);
    $fake->assertAllConsumed();
});

it('announces the run starting and completing', function () {
    Event::fake([RunStatusChanged::class]);
    fakeAi(AiResponse::withText('Done.'));

    runner()->start(newRun(newAgent()));

    Event::assertDispatched(RunStatusChanged::class, fn ($e) => $e->to === RunStatus::Running);
    Event::assertDispatched(RunStatusChanged::class, fn ($e) => $e->to === RunStatus::Completed);
});