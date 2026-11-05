<?php

namespace App\Ai;

use App\Contracts\AiProvider;
use RuntimeException;

class FakeAiProvider implements AiProvider
{
    /** @var list<array{messages: array, tools: array, system: ?string}> */
    public array $calls = [];

    /** @param list<AiResponse> $responses */
    public function __construct(private array $responses = []) {}

    public function complete(array $messages, array $tools = [], ?string $system = null): AiResponse
    {
        $this->calls[] = ['messages' => $messages, 'tools' => $tools, 'system' => $system];

        if ($this->responses === []) {
            throw new RuntimeException('FakeAiProvider has no more scripted responses.');
        }

        return array_shift($this->responses);
    }

    /** Fail loudly if the script was not fully used. */
    public function assertAllConsumed(): void
    {
        if ($this->responses !== []) {
            throw new RuntimeException(sprintf(
                'FakeAiProvider still has %d unused scripted response(s).',
                count($this->responses),
            ));
        }
    }
}