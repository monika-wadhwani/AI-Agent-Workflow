<?php

use App\Ai\AiResponse;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->workspace = workspace('Acme');
});

it('lets a member start a run and returns the result', function () {
    actingAsMemberOf($this->workspace);
    actingInWorkspace($this->workspace);
    $agent = newAgent();
    $fake = fakeAi(AiResponse::withText('All done.'));

    $this->postJson("/api/agents/{$agent->id}/runs", ['input' => 'Do the thing'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.output', 'All done.');

    $fake->assertAllConsumed();
});

it('returns a run that is waiting for approval', function () {
    Mail::fake();
    actingAsMemberOf($this->workspace);
    actingInWorkspace($this->workspace);
    $agent = newAgent(['send_email']);
    fakeAi(AiResponse::withToolCall('send_email', [
        'to' => 'boss@example.com',
        'subject' => 'Hi',
        'body' => 'Hello',
    ]));

    $this->postJson("/api/agents/{$agent->id}/runs", ['input' => 'Email the boss'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'waiting_approval')
        ->assertJsonPath('data.steps.0.tool_name', 'send_email')
        ->assertJsonPath('data.steps.0.status', 'waiting_approval');

    Mail::assertNothingSent();
});

it('rejects a run with no input', function () {
    actingAsMemberOf($this->workspace);
    actingInWorkspace($this->workspace);
    $agent = newAgent();

    $this->postJson("/api/agents/{$agent->id}/runs", [])->assertUnprocessable();
});

it('cannot start a run on an agent from another workspace', function () {
    actingInWorkspace(workspace('Other'));
    $agent = newAgent();

    actingAsMemberOf($this->workspace);
    fakeAi();

    $this->postJson("/api/agents/{$agent->id}/runs", ['input' => 'Hi'])->assertNotFound();
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