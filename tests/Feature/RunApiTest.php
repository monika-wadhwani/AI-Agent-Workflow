<?php

use App\Jobs\ExecuteRun;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->workspace = workspace('Acme');
});

it('accepts a run and queues it instead of running it during the request', function () {
    Queue::fake();
    actingAsMemberOf($this->workspace);
    actingInWorkspace($this->workspace);
    $agent = newAgent();

    $this->postJson("/api/agents/{$agent->id}/runs", ['input' => 'Do the thing'])
        ->assertStatus(202)
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.input', 'Do the thing');

    Queue::assertPushed(ExecuteRun::class, 1);
    Queue::assertPushed(ExecuteRun::class, fn (ExecuteRun $job) => $job->workspaceId === $this->workspace->id);
});

it('rejects a run with no input and queues nothing', function () {
    Queue::fake();
    actingAsMemberOf($this->workspace);
    actingInWorkspace($this->workspace);
    $agent = newAgent();

    $this->postJson("/api/agents/{$agent->id}/runs", [])->assertUnprocessable();

    Queue::assertNothingPushed();
});

it('cannot start a run on an agent from another workspace', function () {
    Queue::fake();
    actingInWorkspace(workspace('Other'));
    $agent = newAgent();

    actingAsMemberOf($this->workspace);

    $this->postJson("/api/agents/{$agent->id}/runs", ['input' => 'Hi'])->assertNotFound();

    Queue::assertNothingPushed();
});

it('shows a run to a member', function () {
    actingAsMemberOf($this->workspace);
    actingInWorkspace($this->workspace);
    $run = newRun(newAgent());

    $this->getJson("/api/runs/{$run->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $run->id)
        ->assertJsonPath('data.status', 'pending');
});

it('hides a run that belongs to another workspace', function () {
    actingInWorkspace(workspace('Other'));
    $run = newRun(newAgent());

    actingAsMemberOf($this->workspace);

    $this->getJson("/api/runs/{$run->id}")->assertNotFound();
});