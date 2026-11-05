<?php

namespace App\Contracts;

use App\Ai\AiResponse;

interface AiProvider
{
    /**
     * @param  list<array<string, mixed>>  $messages  role: user|assistant|tool
     * @param  list<array{name: string, description: string, parameters: array}>  $tools
     */
    public function complete(array $messages, array $tools = [], ?string $system = null): AiResponse;
}