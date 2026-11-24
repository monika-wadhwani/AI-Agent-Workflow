<?php

use App\Ai\AnthropicAiProvider;
use App\Ai\Tools\HttpGetTool;

beforeEach(function () {
    if (! getenv('RUN_LIVE_TESTS')) {
        $this->markTestSkipped('Live tests are opt-in: set RUN_LIVE_TESTS=1.');
    }

    if (! config('services.anthropic.key')) {
        $this->markTestSkipped('ANTHROPIC_API_KEY is not set.');
    }
});

function liveProvider(): AnthropicAiProvider
{
    return new AnthropicAiProvider(
        apiKey: (string) config('services.anthropic.key'),
        model: (string) config('services.anthropic.model'),
        maxTokens: 300,
    );
}

it('gets a real answer from the real model', function () {
    $response = liveProvider()->complete([
        ['role' => 'user', 'content' => 'Reply with exactly one word: pong'],
    ]);

    expect(strtolower($response->text))->toContain('pong');
});

it('asks for a tool when the task needs one', function () {
    $tool = new HttpGetTool;

    $response = liveProvider()->complete(
        [['role' => 'user', 'content' => 'Use the tool to fetch https://example.com and tell me the page title.']],
        [['name' => $tool->name(), 'description' => $tool->description(), 'parameters' => $tool->parameters()]],
    );

    expect($response->hasToolCalls())->toBeTrue()
        ->and($response->toolCalls[0]->name)->toBe('http_get');
});