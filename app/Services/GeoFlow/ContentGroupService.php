<?php

namespace App\Services\GeoFlow;

use App\Models\Article;
use App\Models\ContentGroup;
use App\Models\ContentVariant;
use App\Support\GeoFlow\ContentPlatformCatalog;
use Illuminate\Support\Facades\DB;

class ContentGroupService
{
    public function __construct(private readonly ContentPlatformCatalog $platformCatalog) {}

    public function ensureForArticle(Article $article): ContentGroup
    {
        return DB::transaction(function () use ($article): ContentGroup {
            $lockedArticle = Article::query()->whereKey($article->getKey())->lockForUpdate()->firstOrFail();
            $contentGroup = ContentGroup::query()->firstOrCreate(
                ['main_article_id' => (int) $lockedArticle->id],
                [
                    'task_id' => $lockedArticle->task_id,
                    'name' => (string) $lockedArticle->title,
                    'status' => ContentGroup::STATUS_DRAFT,
                ],
            );

            foreach ($this->platformCatalog->all() as $platform => $template) {
                $isWordPress = $platform === 'wordpress';
                ContentVariant::query()->firstOrCreate(
                    [
                        'content_group_id' => (int) $contentGroup->id,
                        'platform' => $platform,
                    ],
                    [
                        'source_article_id' => (int) $lockedArticle->id,
                        'title' => $isWordPress ? (string) $lockedArticle->title : '',
                        'excerpt' => $isWordPress ? (string) ($lockedArticle->excerpt ?? '') : null,
                        'content' => $isWordPress ? (string) $lockedArticle->content : null,
                        'status' => $isWordPress ? ContentVariant::STATUS_READY : ContentVariant::STATUS_PENDING,
                        'review_status' => (string) $lockedArticle->review_status,
                        'template_version' => (string) ($template['template_version'] ?? '1.0'),
                    ],
                );
            }

            return $contentGroup->load(['mainArticle', 'task', 'variants']);
        });
    }

    /** @return array<int, string> Variant id => stale reason. */
    public function staleVariantReasons(ContentGroup $contentGroup): array
    {
        $article = $contentGroup->mainArticle;
        if (! $article) {
            return [];
        }

        $sourceHash = hash('sha256', implode("\n", array_filter([
            (string) $article->title,
            (string) $article->excerpt,
            (string) $article->content,
        ])));

        return $contentGroup->variants
            ->filter(fn (ContentVariant $variant) => $variant->platform !== 'wordpress'
                && filled($variant->source_content_hash)
                && $variant->source_content_hash !== $sourceHash)
            ->mapWithKeys(fn (ContentVariant $variant) => [$variant->id => '主文章已更新；平台稿仍保留人工修改，需人工决定是否重新生成。'])
            ->all();
    }
}
