<?php

namespace App\Enums;

enum RunStatus: string
{
    case Pending = 'pending';
    case WaitingApproval = 'waiting_approval';
    case Completed = 'completed';
    case Running = 'running';
    case Failed = 'failed';

    public function allowedNext(): array
    {
        return match ($this) {
            self::Pending => [self::Running, self::Failed],
            self::Completed, self::Failed => [],
            self::Running => [self::Completed, self::WaitingApproval, self::Failed],
            self::WaitingApproval => [self::Running, self::Failed],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedNext(), true);
    }
}