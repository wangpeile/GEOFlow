<?php

namespace App\Enums;

enum ContentStageStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Skipped = 'skipped';
    case WaitingInput = 'waiting_input';
    case Cancelled = 'cancelled';
}
