<?php

use App\Agents\AgentRunner;
use App\Ai\AiResponse;
use App\Enums\RunStatus;
use App\Jobs\ExecuteRun;
use App\Support\CurrentWorkspace;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->workspace = workspace('Acme');
    actingInWorkspace($this->workspace);
});

/** Do what a queue worker does: build the job's helpers and call handle(). */
function runJob(ExecuteRun $job): void
{
    app()->call([$job, 'handle']);
}

it('runs a queued run to completion', function () {
    $fake = fakeAi(AiResponse::withText('All done.'));
    $run = newRun(newAgent());

    runJob(new ExecuteRun($run->id, $this->workspace->id));

    $run->refresh();
    expect($run->status)->toBe(RunStatus::Completed)
        ->and($run->output)->toBe('All done.');
    $fake->assertAllConsumed();
});

it('still pauses for human approval when it runs in the background', function () {
    Mail::fake();
    fakeAi(AiResponse::withToolCall('send_email', [
        'to' => 'boss@example.com',
        'subject' => 'Report',
        'body' => 'Numbers attached.',
    ]));
    $run = newRun(newAgent(['send_email']));

    runJob(new ExecuteRun($run->id, $this->workspace->id));

    expect($run->refresh()->status)->toBe(RunStatus::WaitingApproval);
    Mail::assertNothingSent();
});

it('skips a run that was already picked up when the queue delivers the job twice', function () {
    $fake = fakeAi(AiResponse::withText('Once.'));
    $run = newRun(newAgent());
    $job = new ExecuteRun($run->id, $this->workspace->id);

    runJob($job);
    runJob($job); // delivered a second time

    expect($fake->calls)->toHaveCount(1);
    $fake->assertAllConsumed();
});

it('works inside the workspace it was queued for and restores the previous one', function () {
    $other = workspace('Other');
    fakeAi(AiResponse::withText('Done.'));
    $run = newRun(newAgent()); // belongs to Acme

    $current = app(CurrentWorkspace::class);
    $current->set($other); // the worker was last busy with another customer

    runJob(new ExecuteRun($run->id, $this->workspace->id));

    expect($current->id())->toBe($other->id)
        ->and($run->fresh()->status)->toBe(RunStatus::Completed);
});

it('restores the previous workspace even when the job crashes halfway', function () {
    $other = workspace('Other');
    $run = newRun(newAgent());
    $this->mock(AgentRunner::class, fn ($mock) => $mock->shouldReceive('execute')->andThrow(new RuntimeException('boom')));

    $current = app(CurrentWorkspace::class);
    $current->set($other);

    expect(fn () => runJob(new ExecuteRun($run->id, $this->workspace->id)))
        ->toThrow(RuntimeException::class, 'boom');

    expect($current->id())->toBe($other->id);
});

it('marks the run failed when the queue gives up on the job', function () {
    $run = newRun(newAgent());

    (new ExecuteRun($run->id, $this->workspace->id))->failed(new RuntimeException('Job timed out'));

    $run->refresh();
    expect($run->status)->toBe(RunStatus::Failed)
        ->and($run->error)->toContain('Job timed out');
});

it('leaves a finished run alone when the failure hook runs late', function () {
    fakeAi(AiResponse::withText('Done.'));
    $run = newRun(newAgent());
    $job = new ExecuteRun($run->id, $this->workspace->id);
    runJob($job);

    $job->failed(new RuntimeException('too late'));

    $run->refresh();
    expect($run->status)->toBe(RunStatus::Completed)
        ->and($run->error)->toBeNull();
});

it('is set up to never retry on its own', function () {
    $job = new ExecuteRun(1, 1);

    expect($job->tries)->toBe(1)
        ->and($job->timeout)->toBe(120);
});