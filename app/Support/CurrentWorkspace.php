<?php

namespace App\Support;

use App\Models\Workspace;
use Closure;

class CurrentWorkspace
{
    private ?int $id = null;

    public function id(): ?int
    {
        return $this->id;
    }

    public function set(Workspace|int|null $workspace): void
    {
        $this->id = $workspace instanceof Workspace ? $workspace->id : $workspace;
    }

    /** Run a callback as a given workspace, then restore the previous one. */
    public function runAs(Workspace $workspace, Closure $callback): mixed
    {
        $previous = $this->id;
        $this->id = $workspace->id;

        try {
            return $callback();
        } finally {
            $this->id = $previous;
        }
    }
}