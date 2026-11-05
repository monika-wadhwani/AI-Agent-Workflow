<?php

use App\Ai\AiResponse;
use App\Ai\FakeAiProvider;

it('returns scripted responses in order', function () {
    $fake = new FakeAiProvider([
        AiResponse::withToolCall('http_get', ['url' => 'https://example.com'], 'call_1'),
        AiResponse::withText('All done.'),
    ]);

    $first = $fake->complete([['role' => 'user', 'content' => 'Check the site']]);
    $second = $fake->complete([]);

    expect($first->hasToolCalls())->toBeTrue()
    ->and($first->toolCalls[0]->name)->toBe('http_get')
    ->and($first->toolCalls[0]->id)->toBe('call_1')
    ->and($second->hasToolCalls())->toBeFalse()
    ->and($second->text)->toBe('All done.');
});

it('records every call so tests can inspect what the model was shown', function () {
    $fake = new FakeAiProvider([AiResponse::withText('Hi')]);

    $fake->complete([['role' => 'user', 'content' => 'Hello']], [], 'Be helpful.');

    expect($fake->calls)->toHaveCount(1)
        ->and($fake->calls[0]['system'])->toBe('Be helpful.')
        ->and($fake->calls[0]['messages'][0]['content'])->toBe('Hello');
});

it('throws when the script runs out', function () {
    $fake = new FakeAiProvider([]);

    $fake->complete([]);
})->throws(RuntimeException::class, 'no more scripted responses');

it('generates an id for a tool call when none is given', function () {
    $response = AiResponse::withToolCall('http_get');

    expect($response->toolCalls[0]->id)->toStartWith('call_');
});

it('passes assertAllConsumed when every scripted response was used', function () {
    $fake = new FakeAiProvider([AiResponse::withText('Hi')]);
    $fake->complete([]);

    $fake->assertAllConsumed();

    expect($fake->calls)->toHaveCount(1);
});

it('fails assertAllConsumed when scripted responses were left unused', function () {
    $fake = new FakeAiProvider([AiResponse::withText('One'), AiResponse::withText('Two')]);
    $fake->complete([]);

    $fake->assertAllConsumed();
})->throws(RuntimeException::class, '1 unused');