<?php

use App\Ai\Tools\SendEmailTool;
use App\Mail\AgentEmail;
use Illuminate\Support\Facades\Mail;

it('always requires human approval', function () {
    expect((new SendEmailTool)->requiresApproval())->toBeTrue();
});

it('sends the email', function () {
    Mail::fake();

    $result = (new SendEmailTool)->execute([
        'to' => 'boss@example.com',
        'subject' => 'Weekly report',
        'body' => 'Numbers attached.',
    ]);

    expect($result)->toBe('Email sent to boss@example.com.');
    Mail::assertSent(AgentEmail::class, fn ($mail) => $mail->hasTo('boss@example.com'));
});

it('rejects an invalid address without sending', function () {
    Mail::fake();

    expect(fn () => (new SendEmailTool)->execute(['to' => 'not-an-email', 'subject' => 'x', 'body' => 'y']))
        ->toThrow(InvalidArgumentException::class);

    Mail::assertNothingSent();
});