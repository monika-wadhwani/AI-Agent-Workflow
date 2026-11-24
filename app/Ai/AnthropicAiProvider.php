<?php

namespace App\Ai;

use App\Contracts\AiProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AnthropicAiProvider implements AiProvider
{
    public function __construct(
        private string $apiKey,
        private string $model,
        private int $maxTokens = 1024,
        private string $baseUrl = 'https://api.anthropic.com',
    ) {}

    public function complete(array $messages, array $tools = [], ?string $system = null): AiResponse
    {
        if ($this->apiKey === '') {
            throw new RuntimeException('ANTHROPIC_API_KEY is not set.');
        }

        $payload = array_filter([
            'model' => $this->model,
            'max_tokens' => $this->maxTokens,
            'system' => $system,
            'messages' => $this->toAnthropicMessages($messages),
            'tools' => $this->toAnthropicTools($tools),
        ], fn ($value) => $value !== null && $value !== [] && $value !== '');

        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
        ])->timeout(60)->acceptJson()->post($this->baseUrl.'/v1/messages', $payload);

        if ($response->failed()) {
            throw new RuntimeException(sprintf(
                'Anthropic API error (%d): %s',
                $response->status(),
                $response->json('error.message', 'no details'),
            ));
        }

        return $this->toAiResponse($response->json());
    }

    /** Our diary-style messages -> Anthropic's message format. */
    private function toAnthropicMessages(array $messages): array
    {
        $converted = [];

        foreach ($messages as $message) {
            $converted[] = match ($message['role']) {
                'user' => ['role' => 'user', 'content' => (string) $message['content']],
                'assistant' => ['role' => 'assistant', 'content' => $this->assistantBlocks($message)],
                'tool' => ['role' => 'user', 'content' => [[
                    'type' => 'tool_result',
                    'tool_use_id' => $message['tool_call_id'],
                    'content' => (string) $message['content'],
                ]]],
                default => throw new RuntimeException("Unknown message role [{$message['role']}]."),
            };
        }

        return $converted;
    }

    private function assistantBlocks(array $message): array
    {
        $blocks = [];

        if (! empty($message['content'])) {
            $blocks[] = ['type' => 'text', 'text' => $message['content']];
        }

        foreach ($message['tool_calls'] ?? [] as $call) {
            $blocks[] = [
                'type' => 'tool_use',
                'id' => $call['id'],
                'name' => $call['name'],
                'input' => (object) $call['arguments'], // must be a JSON object, even when empty
            ];
        }

        return $blocks;
    }

    private function toAnthropicTools(array $tools): array
    {
        return array_map(function (array $tool) {
            $schema = $tool['parameters'] + ['type' => 'object'];

            if (isset($schema['properties']) && $schema['properties'] === []) {
                $schema['properties'] = (object) [];
            }

            return [
                'name' => $tool['name'],
                'description' => $tool['description'],
                'input_schema' => $schema,
            ];
        }, $tools);
    }

    /** Anthropic's answer -> our AiResponse. */
    private function toAiResponse(array $body): AiResponse
    {
        $text = '';
        $toolCalls = [];

        foreach ($body['content'] ?? [] as $block) {
            if (($block['type'] ?? null) === 'text') {
                $text .= $block['text'];
            } elseif (($block['type'] ?? null) === 'tool_use') {
                $toolCalls[] = new ToolCall($block['id'], $block['name'], $block['input'] ?? []);
            }
        }

        return new AiResponse(
            text: $text === '' ? null : $text,
            toolCalls: $toolCalls,
        );
    }
}