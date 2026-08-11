<?php

namespace App\Enums;

enum ContentProductionStatus: string
{
    case Draft = 'draft';
    case Queued = 'queued';
    case Running = 'running';
    case WaitingInput = 'waiting_input';
    case WaitingReview = 'waiting_review';
    case Failed = 'failed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
