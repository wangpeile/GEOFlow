<?php

namespace App\Services\GeoFlow;

use App\Models\Admin;
use App\Models\ContentVariant;
use App\Models\ContentVariantReview;
use App\Models\ContentVariantVersion;
use App\Support\GeoFlow\ContentPlatformCatalog;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ContentVariantWorkflowService
{
    public function __construct(
        private readonly ContentPlatformCatalog $platformCatalog,
        private readonly ContentVariantFactChecker $factChecker,
        private readonly ContentVariantQualityChecker $qualityChecker,
    ) {}

    /** @param array<string, mixed> $data */
    public function update(ContentVariant $variant, Admin $admin, array $data): ContentVariant
    {
        $this->assertEditable($variant);
        $variant->loadMissing('sourceArticle');
        if (! $variant->sourceArticle) {
            throw new RuntimeException('源文章不存在，无法保存平台版本。');
        }

        $tags = array_values(array_unique(array_filter(array_map('trim', $data['tags'] ?? []))));
        $images = array_values(array_unique(array_filter(array_map('trim', $data['image_requirements'] ?? []))));
        $sourceContent = $this->sourceContent($variant);
        $generatedContent = implode("\n", array_filter([$data['title'], $data['excerpt'] ?? null, $data['content']]));
        $factCheck = $this->factChecker->inspect($sourceContent, $generatedContent);
        $qualityCheck = $this->qualityChecker->inspect(
            $data['title'],
            $data['content'],
            $this->platformCatalog->get($variant->platform),
            $factCheck,
            $data['excerpt'] ?? null,
            $tags,
            $images,
        );

        return DB::transaction(function () use ($variant, $admin, $data, $tags, $images, $factCheck, $qualityCheck, $sourceContent): ContentVariant {
            $locked = ContentVariant::query()->whereKey($variant->id)->lockForUpdate()->firstOrFail();
            $this->assertEditable($locked);
            if ($locked->version !== (int) $data['current_version']) {
                throw new RuntimeException('平台版本已被其他操作更新，请刷新后再编辑。');
            }
            $nextVersion = ((int) ContentVariantVersion::query()
                ->where('content_variant_id', $locked->id)
                ->max('version')) + 1;
            $meta = array_merge($locked->generation_meta ?? [], [
                'operation' => 'manual_edit',
                'edited_at' => now()->toIso8601String(),
                'source_content_hash' => hash('sha256', $sourceContent),
            ]);

            ContentVariantVersion::query()->create([
                'content_variant_id' => $locked->id,
                'version' => $nextVersion,
                'change_type' => 'manual_edit',
                'title' => $data['title'],
                'excerpt' => $data['excerpt'] ?? null,
                'content' => $data['content'],
                'tags' => $tags,
                'image_requirements' => $images,
                'template_version' => $locked->template_version,
                'generation_meta' => $meta,
                'quality_check' => $qualityCheck,
                'created_by' => $admin->id,
            ]);

            $locked->update([
                'title' => $data['title'],
                'excerpt' => $data['excerpt'] ?? null,
                'content' => $data['content'],
                'tags' => $tags,
                'image_requirements' => $images,
                'version' => $nextVersion,
                'generation_meta' => $meta,
                'source_content_hash' => hash('sha256', $sourceContent),
                'fact_check' => $factCheck,
                'quality_check' => $qualityCheck,
                'status' => ContentVariant::STATUS_REVIEW_PENDING,
                'review_status' => ContentVariant::REVIEW_PENDING,
                'reviewed_by' => null,
                'reviewed_at' => null,
                'review_note' => null,
                'failure_message' => null,
            ]);

            return $locked->fresh(['versions.creator', 'reviewer', 'reviews.reviewer']);
        });
    }

    public function review(ContentVariant $variant, Admin $admin, int $expectedVersion, string $decision, ?string $note): ContentVariant
    {
        $this->assertEditable($variant);

        return DB::transaction(function () use ($variant, $admin, $expectedVersion, $decision, $note): ContentVariant {
            $locked = ContentVariant::query()->whereKey($variant->id)->lockForUpdate()->firstOrFail();
            $this->assertEditable($locked);
            if ($locked->version !== $expectedVersion) {
                throw new RuntimeException('待审核版本已更新，请刷新后重新审核。');
            }
            if ($locked->title === '' || trim((string) $locked->content) === '') {
                throw new RuntimeException('平台版本尚未生成，无法审核。');
            }
            if ($decision === ContentVariant::REVIEW_APPROVED && ! ($locked->quality_check['passed'] ?? false)) {
                throw new RuntimeException('质量检查未通过，修复阻断项后才能审核通过。');
            }

            $version = ContentVariantVersion::query()
                ->where('content_variant_id', $locked->id)
                ->where('version', $locked->version)
                ->first();
            ContentVariantReview::query()->create([
                'content_variant_id' => $locked->id,
                'content_variant_version_id' => $version?->id,
                'reviewer_id' => $admin->id,
                'decision' => $decision,
                'note' => $note,
                'quality_check' => $locked->quality_check,
            ]);

            $locked->update([
                'review_status' => $decision,
                'status' => $decision === ContentVariant::REVIEW_APPROVED
                    ? ContentVariant::STATUS_READY
                    : ContentVariant::STATUS_REVIEW_PENDING,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'review_note' => $note,
            ]);

            return $locked->fresh(['reviewer', 'reviews.reviewer']);
        });
    }

    private function assertEditable(ContentVariant $variant): void
    {
        if ($variant->platform === 'wordpress') {
            throw new RuntimeException('WordPress 主文章不能在平台改写流程中编辑或审核。');
        }
        if ($variant->status === ContentVariant::STATUS_GENERATING) {
            throw new RuntimeException('该平台版本正在生成，请稍后再操作。');
        }
        if ($variant->status === ContentVariant::STATUS_PUBLISHED) {
            throw new RuntimeException('已发布的平台版本不能直接改写，请先创建新的发布修订流程。');
        }
    }

    private function sourceContent(ContentVariant $variant): string
    {
        return implode("\n", array_filter([
            (string) $variant->sourceArticle?->title,
            (string) $variant->sourceArticle?->excerpt,
            (string) $variant->sourceArticle?->content,
        ]));
    }
}
