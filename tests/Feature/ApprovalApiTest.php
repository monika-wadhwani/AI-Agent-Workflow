<?php

use App\Ai\AiResponse;
use App\Enums\RunStatus;
use App\Enums\WorkspaceRole;
use App\Mail\AgentEmail;
use App\Models\Workspace;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    $this->workspace = workspace('Acme');
});

/** A run that has already paused, waiting for a human. */
function pausedRun(Workspace $workspace): array
{
    actingInWorkspace($workspace);
    $agent = newAgent(['send_email']);
    fakeAi(
        AiResponse::withToolCall('send_email', [
            'to' => 'boss@example.com',
            'subject' => 'Report',
            'body' => 'Numbers attached.',
        ], 'call_1'),
        AiResponse::withText('Report sent.'),
    );
    $run = runner()->start(newRun($agent));

    return [$run, $run->steps()->first()];
}

it('lets an owner approve a waiting step, which sends the email', function () {
    [$run, $step] = pausedRun($this->workspace);
    actingAsMemberOf($this->workspace, WorkspaceRole::Owner);

    $this->postJson("/api/steps/{$step->id}/approve")
        ->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.output', 'Report sent.');

    Mail::assertSent(AgentEmail::class, 1);
});

it('stops a plain member from approving', function () {
    [$run, $step] = pausedRun($this->workspace);
    actingAsMemberOf($this->workspace, WorkspaceRole::Member);

    $this->postJson("/api/steps/{$step->id}/approve")->assertForbidden();

    Mail::assertNothingSent();
});

it('lets an owner reject, and nothing is sent', function () {
    [$run, $step] = pausedRun($this->workspace);
    actingAsMemberOf($this->workspace, WorkspaceRole::Owner);

    $this->postJson("/api/steps/{$step->id}/reject")
        ->assertOk()
        ->assertJsonPath('data.steps.0.status', 'rejected');

    Mail::assertNothingSent();
});

it('answers 409 when the step was already decided', function () {
    [$run, $step] = pausedRun($this->workspace);
    actingAsMemberOf($this->workspace, WorkspaceRole::Owner);
    $this->postJson("/api/steps/{$step->id}/approve")->assertOk();

    $this->postJson("/api/steps/{$step->id}/approve")->assertStatus(409);

    Mail::assertSent(AgentEmail::class, 1); // never sent twice
});

it('refuses a late approval after the run has failed', function () {
    [$run, $step] = pausedRun($this->workspace);
    $run->transitionTo(RunStatus::Failed);
    actingAsMemberOf($this->workspace, WorkspaceRole::Owner);

    $this->postJson("/api/steps/{$step->id}/approve")->assertStatus(409);

    Mail::assertNothingSent();
});

it('cannot touch a step from another workspace', function () {
    [$run, $step] = pausedRun(workspace('Other'));
    actingAsMemberOf($this->workspace, WorkspaceRole::Owner);

    $this->postJson("/api/steps/{$step->id}/approve")->assertNotFound();

    Mail::assertNothingSent();
});