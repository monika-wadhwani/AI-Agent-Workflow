<?php

namespace Tests\Support;

use App\Contracts\Tool;
use Closure;

class FakeTool implements Tool
{
    public int $executions = 0;

    /** @var list<array<string, mixed>> */
    public array $received = [];

    public function __construct(
        private string $name = 'test_tool',
        private bool $needsApproval = false,
        private ?Closure $handler = null,
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function description(): string
    {
        return 'A tool used only in tests.';
    }

    public function parameters(): array
    {
        return ['type' => 'object', 'properties' => []];
    }

    public function requiresApproval(): bool
    {
        return $this->needsApproval;
    }

    public function execute(array $arguments): string
    {
        $this->executions++;
        $this->received[] = $arguments;

        return $this->handler ? ($this->handler)($arguments) : 'ok';
    }
}