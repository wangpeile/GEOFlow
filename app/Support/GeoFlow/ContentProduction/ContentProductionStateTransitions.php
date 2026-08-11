<?php

namespace App\Support\GeoFlow\ContentProduction;

use App\Enums\ContentProductionStatus;
use App\Enums\ContentStageStatus;

final class ContentProductionStateTransitions
{
    /**
     * @var array<string, list<ContentProductionStatus>>
     */
    private const PROJECT_TRANSITIONS = [
        'draft' => [ContentProductionStatus::Queued, ContentProductionStatus::Cancelled],
        'queued' => [ContentProductionStatus::Running, ContentProductionStatus::Failed, ContentProductionStatus::Cancelled],
        'running' => [
            ContentProductionStatus::WaitingInput,
            ContentProductionStatus::WaitingReview,
            ContentProductionStatus::Failed,
            ContentProductionStatus::Completed,
            ContentProductionStatus::Cancelled,
        ],
        'waiting_input' => [ContentProductionStatus::Running, ContentProductionStatus::Cancelled],
        'waiting_review' => [ContentProductionStatus::Running, ContentProductionStatus::Failed, ContentProductionStatus::Cancelled],
        'failed' => [ContentProductionStatus::Queued, ContentProductionStatus::Cancelled],
        'completed' => [],
        'cancelled' => [],
    ];

    /**
     * @var array<string, list<ContentStageStatus>>
     */
    private const STAGE_TRANSITIONS = [
        'pending' => [ContentStageStatus::Running, ContentStageStatus::Skipped, ContentStageStatus::Cancelled],
        'running' => [
            ContentStageStatus::Succeeded,
            ContentStageStatus::Failed,
            ContentStageStatus::WaitingInput,
            ContentStageStatus::Cancelled,
        ],
        'succeeded' => [],
        'failed' => [ContentStageStatus::Pending, ContentStageStatus::Cancelled],
        'skipped' => [],
        'waiting_input' => [ContentStageStatus::Running, ContentStageStatus::Cancelled],
        'cancelled' => [],
    ];

    public function canTransitionProject(
        ContentProductionStatus $from,
        ContentProductionStatus $to,
    ): bool {
        return in_array($to, self::PROJECT_TRANSITIONS[$from->value], true);
    }

    public function canTransitionStage(
        ContentStageStatus $from,
        ContentStageStatus $to,
    ): bool {
        return in_array($to, self::STAGE_TRANSITIONS[$from->value], true);
    }
}
