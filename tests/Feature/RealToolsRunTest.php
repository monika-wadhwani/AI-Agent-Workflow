<?php

use App\Ai\AiResponse;
use App\Enums\RunStatus;
use App\Enums\StepStatus;
use App\Mail\AgentEmail;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

beforeEach(fn () => actingInWorkspace(workspace()));

it('lets an agent fetch a page and use what it found', function () {
    Http::fake(['https://api.example.com/*' => Http::response('{"status":"ok"}')]);
    $fake = fakeAi(
        AiResponse::withToolCall('http_get', ['url' => 'https://api.example.com/health'], 'call_1'),
        AiResponse::withText('The API is healthy.'),
    );

    $run = runner()->start(newRun(newAgent(['http_get'])));
    $step = $run->steps()->first();

    expect($run->status)->toBe(RunStatus::Completed)
        ->and($step->status)->toBe(StepStatus::Completed)
        ->and($step->output)->toContain('"status":"ok"');
    $fake->assertAllConsumed();
});

it('turns a blocked address into a failed step and never sends a request', function () {
    Http::fake();
    $fake = fakeAi(
        AiResponse::withToolCall('http_get', ['url' => 'http://127.0.0.1/admin']),
        AiResponse::withText('I could not open that.'),
    );

    $run = runner()->start(newRun(newAgent(['http_get'])));
    $step = $run->steps()->first();

    expect($run->status)->toBe(RunStatus::Completed)
        ->and($step->status)->toBe(StepStatus::Failed)
        ->and($step->output)->toStartWith('Error:');
    Http::assertNothingSent();
    $fake->assertAllConsumed();
});

it('does not send the email until a human approves', function () {
    Mail::fake();
    $fake = fakeAi(
        AiResponse::withToolCall('send_email', [
            'to' => 'boss@example.com',
            'subject' => 'Weekly report',
            'body' => 'Numbers attached.',
        ], 'call_1'),
        AiResponse::withText('Report sent.'),
    );

    $run = runner()->start(newRun(newAgent(['send_email'])));

    expect($run->status)->toBe(RunStatus::WaitingApproval);
    Mail::assertNothingSent();

    $run = runner()->approve($run->steps()->first());

    expect($run->status)->toBe(RunStatus::Completed);
    Mail::assertSent(AgentEmail::class, 1);
    $fake->assertAllConsumed();
});

it('records a failed step when an approved email has an invalid address', function () {
    Mail::fake();
    fakeAi(
        AiResponse::withToolCall('send_email', ['to' => 'not-an-email', 'subject' => 'x', 'body' => 'y']),
        AiResponse::withText('That address was invalid.'),
    );
    $run = runner()->start(newRun(newAgent(['send_email'])));

    $run = runner()->approve($run->steps()->first());

    expect($run->status)->toBe(RunStatus::Completed)
        ->and($run->steps()->first()->status)->toBe(StepStatus::Failed);
    Mail::assertNothingSent();
});