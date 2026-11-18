<?php

namespace App\Ai;

final readonly class toolCall
{
    public function __construct(
        public string $id,
        public string $name,
        public array $arguments = [],
    ) {}
}