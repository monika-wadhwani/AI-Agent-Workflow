<?php

namespace App\Ai;

use App\Ai\ToolCall;

final readonly class AiResponse
{
    /** @param list<ToolCall> $toolCalls */
    public function __construct(
        public ?string $text = null,
        public array $toolCalls = [],
    ) {}

    public static function withText(string $text): self
    {
        return new self(text: $text);
    }

    /** @param array<string, mixed> $arguments */
    public static function withToolCall(string $name, array $arguments = [], ?string $id = null): self
    {
        return new self(toolCalls: [
            new ToolCall($id ?? 'call_'.bin2hex(random_bytes(4)), $name, $arguments),
        ]);
    }

    public function hasToolCalls(): bool
    {
        return $this->toolCalls !== [];
    }
}