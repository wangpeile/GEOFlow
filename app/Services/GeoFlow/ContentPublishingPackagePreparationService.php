<?php

namespace App\Services\GeoFlow;

use App\Enums\TaskPipelineMode;
use App\Models\Article;
use App\Models\ContentGroup;

class ContentPublishingPackagePreparationService
{
    public function __construct(private readonly ContentGroupService $contentGroupService) {}

    /**
     * Create the publication package only for an opted-in production plan.
     *
     * This deliberately prepares version slots instead of generating platform
     * copy. Generation remains an explicit action in the publishing package,
     * so approving an article never creates an unexpected model request.
     */
    public function prepareAfterApproval(Article $article): ?ContentGroup
    {
        $article->loadMissing('task');
        $task = $article->task;

        if ($task === null
            || $task->pipeline_mode !== TaskPipelineMode::ContentProduction
            || ! data_get($task->automation_settings, 'create_publishing_package_after_review', false)
            || ! in_array((string) $article->review_status, ['approved', 'auto_approved'], true)) {
            return null;
        }

        return $this->contentGroupService->ensureForArticle($article);
    }
}
