<?php

namespace App\Contracts;

interface Tool
{
    /** Unique snake_case name the model uses to call this tool. */
    public function name(): string;

    public function description(): string;

    /** JSON Schema describing the arguments. */
    public function parameters(): array;

    /** Risky tools pause the run until a human approves. */
    public function requiresApproval(): bool;

    /** @param array<string, mixed> $arguments */
    public function execute(array $arguments): string;
}