<?php

use App\Ai\AnthropicAiProvider;
use App\Contracts\AiProvider;
use App\Enums\RunStatus;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function provider(): AnthropicAiProvider
{
    return new AnthropicAiProvider(apiKey: 'test-key', model: 'test-model', maxTokens: 500);
}

it('returns the text the model answered with', function () {
    Http::fake(['api.anthropic.com/*' => Http::response([
        'content' => [['type' => 'text', 'text' => 'Hello there.']],
        'stop_reason' => 'end_turn',
    ])]);

    $response = provider()->complete([['role' => 'user', 'content' => 'Hi']]);

    expect($response->text)->toBe('Hello there.')
        ->and($response->hasToolCalls())->toBeFalse();
});

it('returns tool calls when the model wants a tool', function () {
    Http::fake(['api.anthropic.com/*' => Http::response([
        'content' => [
            ['type' => 'text', 'text' => 'Let me check.'],
            ['type' => 'tool_use', 'id' => 'toolu_1', 'name' => 'http_get', 'input' => ['url' => 'https://example.com']],
        ],
        'stop_reason' => 'tool_use',
    ])]);

    $response = provider()->complete([['role' => 'user', 'content' => 'Check the site']]);

    expect($response->hasToolCalls())->toBeTrue()
        ->and($response->toolCalls[0]->id)->toBe('toolu_1')
        ->and($response->toolCalls[0]->name)->toBe('http_get')
        ->and($response->toolCalls[0]->arguments)->toBe(['url' => 'https://example.com']);
});

it('sends the key, model, system prompt and tools in the request', function () {
    Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => 'ok']]])]);

    provider()->complete(
        [['role' => 'user', 'content' => 'Hi']],
        [[
            'name' => 'http_get',
            'description' => 'Fetch',
            'parameters' => ['type' => 'object', 'properties' => ['url' => ['type' => 'string']]],
        ]],
        'Be brief.',
    );

    Http::assertSent(function (Request $request) {
        return $request->url() === 'https://api.anthropic.com/v1/messages'
            && $request->hasHeader('x-api-key', 'test-key')
            && $request->hasHeader('anthropic-version', '2023-06-01')
            && $request['model'] === 'test-model'
            && $request['max_tokens'] === 500
            && $request['system'] === 'Be brief.'
            && $request['tools'][0]['name'] === 'http_get'
            && $request['tools'][0]['input_schema']['type'] === 'object';
    });
});

it('translates our tool messages into the format Anthropic expects', function () {
    Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => 'Done.']]])]);

    provider()->complete([
        ['role' => 'user', 'content' => 'Check the site'],
        [
            'role' => 'assistant',
            'content' => null,
            'tool_calls' => [['id' => 'toolu_1', 'name' => 'http_get', 'arguments' => ['url' => 'https://example.com']]],
        ],
        ['role' => 'tool', 'tool_call_id' => 'toolu_1', 'content' => 'page text'],
    ]);

    Http::assertSent(function (Request $request) {
        $messages = json_decode($request->body(), true, 512, JSON_THROW_ON_ERROR)['messages'];

        return $messages[1]['role'] === 'assistant'
            && $messages[1]['content'][0]['type'] === 'tool_use'
            && $messages[1]['content'][0]['id'] === 'toolu_1'
            && $messages[1]['content'][0]['input'] === ['url' => 'https://example.com']
            && $messages[2]['role'] === 'user'
            && $messages[2]['content'][0]['type'] === 'tool_result'
            && $messages[2]['content'][0]['tool_use_id'] === 'toolu_1'
            && $messages[2]['content'][0]['content'] === 'page text';
    });
});

it('turns an API error into a clear exception', function () {
    Http::fake(['api.anthropic.com/*' => Http::response(
        ['error' => ['type' => 'rate_limit_error', 'message' => 'Too many requests']],
        429,
    )]);

    expect(fn () => provider()->complete([['role' => 'user', 'content' => 'Hi']]))
        ->toThrow(RuntimeException::class, 'Anthropic API error (429): Too many requests');
});

it('refuses to call the API without a key', function () {
    Http::fake();
    $provider = new AnthropicAiProvider(apiKey: '', model: 'test-model');

    expect(fn () => $provider->complete([['role' => 'user', 'content' => 'Hi']]))
        ->toThrow(RuntimeException::class, 'ANTHROPIC_API_KEY');

    Http::assertNothingSent();
});

it('drives a full agent run through the Anthropic adapter', function () {
    actingInWorkspace(workspace());
    app()->instance(AiProvider::class, provider());
    Http::fake([
        'api.anthropic.com/*' => Http::sequence()
            ->push(['content' => [[
                'type' => 'tool_use',
                'id' => 'toolu_1',
                'name' => 'http_get',
                'input' => ['url' => 'https://example.com/health'],
            ]]])
            ->push(['content' => [['type' => 'text', 'text' => 'The site is up.']]]),
        'example.com/*' => Http::response('ok'),
    ]);

    $run = runner()->start(newRun(newAgent(['http_get'])));

    expect($run->status)->toBe(RunStatus::Completed)
        ->and($run->output)->toBe('The site is up.')
        ->and($run->steps()->first()->output)->toBe('ok');
});