<?php

namespace App\Enums;

enum StepStatus: string
{
    case Pending = 'pending';
    case WaitingApproval = 'waiting_approval';
    case Completed = 'completed';
    case Failed = 'failed';
    case Rejected = 'rejected';
}