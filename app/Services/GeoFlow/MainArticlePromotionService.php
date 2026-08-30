<?php

namespace App\Services\GeoFlow;

use App\Enums\ContentProductionStage;
use App\Enums\ContentProductionStatus;
use App\Enums\QualityReportStatus;
use App\Models\Admin;
use App\Models\Article;
use App\Models\ArticleVersion;
use App\Models\Author;
use App\Models\Category;
use App\Models\ContentProduction;
use App\Models\ContentProductionEvent;
use App\Models\QualityReport;
use App\Support\GeoFlow\ArticleWorkflow;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class MainArticlePromotionService
{
    public function promote(Admin $admin, ContentProduction $production): Article
    {
        return DB::transaction(function () use ($admin, $production): Article {
            $lockedProduction = ContentProduction::query()->whereKey($production->id)->lockForUpdate()->firstOrFail();
            if ($lockedProduction->article_id) {
                return Article::query()->findOrFail($lockedProduction->article_id);
            }

            $version = ArticleVersion::query()
                ->where('content_production_id', $lockedProduction->id)
                ->whereNotNull('body')
                ->latest('version')
                ->lockForUpdate()
                ->first();
            if (! $version) {
                throw ValidationException::withMessages(['article' => '请先组装完整文章草稿。']);
            }

            $report = QualityReport::query()
                ->where('content_production_id', $lockedProduction->id)
                ->where('article_version_id', $version->id)
                ->latest('version')
                ->first();
            if (! $report || $report->status === QualityReportStatus::Blocked) {
                throw ValidationException::withMessages(['quality' => '请先完成并通过当前版本的质量检查。']);
            }

            [$categoryId, $authorId] = $this->resolveOwnership($lockedProduction);
            $article = Article::query()->create([
                'title' => $version->title,
                'slug' => ArticleWorkflow::generateUniqueSlug($version->title),
                'excerpt' => $version->summary,
                'content' => $version->body,
                'category_id' => $categoryId,
                'author_id' => $authorId,
                'task_id' => $lockedProduction->task_id,
                'original_keyword' => $lockedProduction->topic,
                'keywords' => $lockedProduction->topic,
                'meta_description' => $version->meta_description,
                'status' => 'draft',
                'review_status' => 'pending',
                'is_ai_generated' => true,
                'published_at' => null,
            ]);

            ArticleVersion::query()
                ->where('content_production_id', $lockedProduction->id)
                ->whereNull('article_id')
                ->update(['article_id' => $article->id, 'synchronized_at' => now()]);

            $lockedProduction->forceFill([
                'article_id' => $article->id,
                'status' => ContentProductionStatus::WaitingReview,
                'current_stage' => ContentProductionStage::QualityGate,
                'last_error_message' => null,
            ])->save();
            ContentProductionEvent::query()->create([
                'content_production_id' => $lockedProduction->id,
                'admin_id' => $admin->id,
                'event' => 'article.promoted',
                'metadata' => ['article_id' => $article->id, 'article_version_id' => $version->id],
            ]);

            return $article;
        });
    }

    /** @return array{int, int} */
    private function resolveOwnership(ContentProduction $production): array
    {
        $production->loadMissing('task');
        $categoryId = (int) (data_get($production->context, 'category_id') ?: $production->task?->fixed_category_id);
        $authorId = (int) (data_get($production->context, 'author_id') ?: $production->task?->custom_author_id ?: $production->task?->author_id);
        if ($categoryId < 1 || ! Category::query()->whereKey($categoryId)->exists()) {
            throw ValidationException::withMessages(['category_id' => '请为工作单指定有效的文章分类。']);
        }
        if ($authorId < 1 || ! Author::query()->whereKey($authorId)->exists()) {
            throw ValidationException::withMessages(['author_id' => '请为工作单指定有效的文章作者。']);
        }

        return [$categoryId, $authorId];
    }
}
