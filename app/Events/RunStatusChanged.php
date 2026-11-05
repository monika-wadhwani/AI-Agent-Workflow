<?php

namespace App\Events;

use App\Enums\RunStatus;
use App\Models\Run;
use Illuminate\Foundation\Events\Dispatchable;

class RunStatusChanged
{
    use Dispatchable;

    public function __construct(
        public readonly Run $run,
        public readonly RunStatus $from,
        public readonly RunStatus $to,
    ) {}
}