<?php

use App\Ai\AiResponse;
use App\Enums\RunStatus;
use App\Jobs\ExecuteRun;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->workspace = actingInWorkspace(workspace());
});

it('queues the run instead of running it inside the request', function () {
    Queue::fake();
    $agent = newAgent();
    actingAsMemberOf($this->workspace);

    $response = $this->postJson("/api/agents/{$agent->id}/runs", ['input' => 'hello']);

    $response->assertStatus(202);
    Queue::assertPushed(ExecuteRun::class, 1);
    expect($response->json('data.status'))->toBe('pending');
});

it('runs the agent to completion when the job executes', function () {
    fakeAi(AiResponse::withText('All done'));
    $run = newRun(newAgent(), 'hello')->refresh();

    expect($run->status)->toBe(RunStatus::Pending);

    app()->call([new ExecuteRun($run->id, $this->workspace->id), 'handle']);

    expect($run->fresh()->status)->toBe(RunStatus::Completed);
});

it('does not run the same run twice', function () {
    $ai = fakeAi(AiResponse::withText('once'));
    $run = newRun(newAgent(), 'hello');
    $job = new ExecuteRun($run->id, $this->workspace->id);

    app()->call([$job, 'handle']);
    app()->call([$job, 'handle']);

    expect($ai->calls)->toHaveCount(1);
});

it('marks the run failed if the job blows up', function () {
    $run = newRun(newAgent(), 'hello');

    (new ExecuteRun($run->id, $this->workspace->id))->failed(new RuntimeException('boom'));

    expect($run->fresh()->status)->toBe(RunStatus::Failed);
});