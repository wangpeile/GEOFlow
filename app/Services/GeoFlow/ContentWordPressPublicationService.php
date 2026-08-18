<?php

namespace App\Services\GeoFlow;

use App\Jobs\ProcessWordPressContentPublicationJob;
use App\Models\Admin;
use App\Models\ArticleDistribution;
use App\Models\ContentGroup;
use App\Models\ContentVariant;
use App\Models\ContentVariantVersion;
use App\Models\DistributionChannel;
use App\Models\DistributionLog;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class ContentWordPressPublicationService
{
    public function __construct(
        private readonly ContentWordPressPayloadBuilder $payloadBuilder,
        private readonly DistributionPublisherManager $publisherManager,
        private readonly PublicationQualityGate $publicationQualityGate,
    ) {}

    /** @return array{blockers:list<string>,warnings:list<string>} */
    public function preflight(ContentGroup $group, ContentVariant $variant): array
    {
        $article = $group->mainArticle;
        $blockers = [];
        $warnings = [];
        if (! $article) {
            $blockers[] = '主文章不存在。';
        }
        if (trim((string) $variant->title) === '') {
            $blockers[] = '文章标题为空。';
        }
        if (trim((string) $variant->content) === '') {
            $blockers[] = '文章正文为空。';
        }
        if (! $article?->category_id) {
            $blockers[] = '尚未设置文章分类。';
        }
        if (trim((string) $variant->excerpt) === '') {
            $blockers[] = '文章摘要为空。';
        }
        if (($variant->tags ?? []) === [] && trim((string) $article?->keywords) === '') {
            $warnings[] = '尚未设置文章标签。';
        }
        if ($article && $article->articleImages()->count() === 0) {
            $warnings[] = '尚未设置特色图片；可发布，但 WordPress 列表页可能缺少封面。';
        }

        return compact('blockers', 'warnings');
    }

    public function queue(ContentGroup $group, ContentVariant $variant, DistributionChannel $channel, Admin $admin, string $mode, ?string $scheduledFor): ArticleDistribution
    {
        if ($variant->content_group_id !== $group->id || $variant->platform !== 'wordpress') {
            throw new RuntimeException('只能发布当前内容组的 WordPress 主文章版本。');
        }
        if (! $channel->isWordPressRest() || $channel->status !== DistributionChannel::STATUS_ACTIVE) {
            throw new RuntimeException('请选择可用的 WordPress 渠道。');
        }
        $group->loadMissing(['mainArticle.articleImages.image']);
        $preflight = $this->preflight($group, $variant);
        if ($preflight['blockers'] !== []) {
            throw new RuntimeException(implode(' ', $preflight['blockers']));
        }
        if ($mode !== 'draft') {
            if ((string) $group->mainArticle?->review_status !== ContentVariant::REVIEW_APPROVED) {
                throw new RuntimeException('主文章尚未审核通过，只能先保存为 WordPress 草稿。');
            }
            $this->publicationQualityGate->check($group->mainArticle);
        }

        return DB::transaction(function () use ($group, $variant, $channel, $admin, $mode, $scheduledFor, $preflight): ArticleDistribution {
            $lockedVariant = ContentVariant::query()->whereKey($variant->id)->lockForUpdate()->firstOrFail();
            $version = ContentVariantVersion::query()
                ->where('content_variant_id', $lockedVariant->id)
                ->where('version', $lockedVariant->version)
                ->first();
            if (! $version) {
                $version = ContentVariantVersion::query()->create([
                    'content_variant_id' => $lockedVariant->id,
                    'version' => $lockedVariant->version,
                    'change_type' => 'source_snapshot',
                    'title' => $lockedVariant->title,
                    'excerpt' => $lockedVariant->excerpt,
                    'content' => $lockedVariant->content,
                    'tags' => $lockedVariant->tags,
                    'image_requirements' => $lockedVariant->image_requirements,
                    'template_version' => $lockedVariant->template_version,
                    'generation_meta' => ['operation' => 'wordpress_source_snapshot'],
                    'quality_check' => $lockedVariant->quality_check,
                    'created_by' => $admin->id,
                ]);
            }

            $distribution = ArticleDistribution::query()
                ->where('article_id', $group->main_article_id)
                ->where('distribution_channel_id', $channel->id)
                ->where('action', 'publish')
                ->lockForUpdate()
                ->first() ?? new ArticleDistribution([
                    'article_id' => $group->main_article_id,
                    'distribution_channel_id' => $channel->id,
                    'action' => 'publish',
                    'idempotency_key' => hash('sha256', 'content-wordpress:'.$group->id.':'.$channel->id),
                ]);
            if ($distribution->status === 'sending') {
                throw new RuntimeException('该 WordPress 发布任务正在执行，请勿重复提交。');
            }
            $meta = array_replace($distribution->remote_meta ?? [], [
                'content_group_id' => (int) $group->id,
                'content_variant_id' => (int) $lockedVariant->id,
                'content_variant_version_id' => (int) $version->id,
                'publication_preflight' => $preflight,
            ]);
            $distribution->forceFill([
                'content_group_id' => $group->id,
                'content_variant_id' => $lockedVariant->id,
                'content_variant_version_id' => $version->id,
                'reviewed_by_admin_id' => $admin->id,
                'publication_mode' => $mode,
                'scheduled_for' => $mode === 'scheduled' ? $scheduledFor : null,
                'published_version' => $version->version,
                'status' => 'queued',
                'next_retry_at' => now(),
                'last_error_message' => null,
                'remote_meta' => $meta,
            ])->save();

            DistributionLog::query()->create([
                'distribution_channel_id' => $channel->id,
                'article_distribution_id' => $distribution->id,
                'article_id' => $group->main_article_id,
                'level' => 'info',
                'event' => 'content.wordpress.queued',
                'message' => 'WordPress 内容版本已进入发布队列',
                'context' => ['content_group_id' => $group->id, 'version' => $version->version, 'mode' => $mode],
                'created_at' => now(),
            ]);
            ProcessWordPressContentPublicationJob::dispatch($distribution->id)->onQueue('distribution')->afterCommit();

            return $distribution;
        });
    }

    public function retry(ArticleDistribution $distribution): ArticleDistribution
    {
        if (! in_array($distribution->status, ['failed', 'queued'], true) || ! $distribution->content_variant_id) {
            throw new RuntimeException('当前发布记录不能重试。');
        }
        $distribution->forceFill(['status' => 'queued', 'next_retry_at' => now(), 'last_error_message' => null])->save();
        ProcessWordPressContentPublicationJob::dispatch($distribution->id)->onQueue('distribution');

        return $distribution;
    }

    public function process(int $distributionId): void
    {
        $distribution = DB::transaction(function () use ($distributionId): ?ArticleDistribution {
            $locked = ArticleDistribution::query()->whereKey($distributionId)->lockForUpdate()->first();
            if (! $locked || $locked->status !== 'queued') {
                return null;
            }
            $locked->forceFill(['status' => 'sending', 'attempt_count' => $locked->attempt_count + 1, 'last_attempt_at' => now()])->save();

            return $locked;
        });
        if (! $distribution) {
            return;
        }

        try {
            $distribution->loadMissing(['article', 'channel', 'contentVariantVersion']);
            if (! $distribution->article || ! $distribution->channel || ! $distribution->contentVariantVersion) {
                throw new RuntimeException('发布快照不完整，无法执行 WordPress 发布。');
            }
            $payload = $this->payloadBuilder->build(
                $distribution->article,
                $distribution->contentVariantVersion,
                (string) $distribution->publication_mode,
                $distribution->scheduled_for?->toIso8601String(),
            );
            $publisher = $this->publisherManager->forChannel($distribution->channel);
            $response = $distribution->wordpressPostId()
                ? $publisher->update($distribution, $payload)
                : $publisher->publish($distribution, $payload);
            DB::transaction(function () use ($distribution, $response): void {
                $locked = ArticleDistribution::query()->whereKey($distribution->id)->lockForUpdate()->firstOrFail();
                $meta = array_replace($locked->remote_meta ?? [], $response['remote_meta'] ?? []);
                $publishedAt = match ($locked->publication_mode) {
                    'immediate' => now(),
                    'scheduled' => $locked->scheduled_for,
                    default => null,
                };
                $locked->forceFill([
                    'status' => 'synced',
                    'remote_id' => (string) ($response['remote_id'] ?? $locked->remote_id),
                    'remote_url' => (string) ($response['remote_url'] ?? $locked->remote_url),
                    'remote_meta' => $meta,
                    'published_at' => $publishedAt,
                    'last_error_message' => null,
                    'next_retry_at' => null,
                ])->save();
                ContentVariant::query()->whereKey($locked->content_variant_id)->update([
                    'status' => $locked->publication_mode === 'immediate' ? ContentVariant::STATUS_PUBLISHED : ContentVariant::STATUS_READY,
                    'published_url' => $locked->remote_url,
                    'published_at' => $publishedAt,
                ]);
                DistributionLog::query()->create([
                    'distribution_channel_id' => $locked->distribution_channel_id,
                    'article_distribution_id' => $locked->id,
                    'article_id' => $locked->article_id,
                    'level' => 'info',
                    'event' => 'content.wordpress.synced',
                    'message' => 'WordPress 内容版本同步成功',
                    'context' => [
                        'content_group_id' => $locked->content_group_id,
                        'content_variant_version_id' => $locked->content_variant_version_id,
                        'publication_mode' => $locked->publication_mode,
                        'remote_id' => $locked->remote_id,
                    ],
                    'created_at' => now(),
                ]);
            });
        } catch (Throwable $exception) {
            $distribution->forceFill([
                'status' => 'failed',
                'last_error_message' => mb_substr($exception->getMessage(), 0, 1000),
                'next_retry_at' => null,
            ])->save();
            DistributionLog::query()->create([
                'distribution_channel_id' => $distribution->distribution_channel_id,
                'article_distribution_id' => $distribution->id,
                'article_id' => $distribution->article_id,
                'level' => 'error',
                'event' => 'content.wordpress.failed',
                'message' => 'WordPress 内容版本同步失败',
                'context' => [
                    'content_group_id' => $distribution->content_group_id,
                    'content_variant_version_id' => $distribution->content_variant_version_id,
                    'publication_mode' => $distribution->publication_mode,
                    'error_type' => $exception::class,
                ],
                'created_at' => now(),
            ]);
            throw $exception;
        }
    }
}
