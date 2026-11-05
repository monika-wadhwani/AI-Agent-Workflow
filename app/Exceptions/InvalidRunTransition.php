<?php

namespace App\Exceptions;

use App\Enums\RunStatus;
use LogicException;

class InvalidRunTransition extends LogicException
{
    public static function between(RunStatus $from, RunStatus $to): self
    {
        return new self("A run cannot move from [{$from->value}] to [{$to->value}].");
    }
}