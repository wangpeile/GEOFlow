<?php

namespace App\Services\GeoFlow;

use App\Contracts\GeoFlow\ContentVariantGenerator;
use App\Models\Admin;
use App\Models\ContentVariant;
use App\Models\ContentVariantVersion;
use App\Support\GeoFlow\ContentPlatformCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class ContentVariantGenerationService
{
    public function __construct(
        private readonly ContentVariantGenerator $generator,
        private readonly ContentPlatformCatalog $platformCatalog,
        private readonly ContentVariantFactChecker $factChecker,
        private readonly ContentVariantQualityChecker $qualityChecker,
    ) {}

    public function generate(ContentVariant $variant, Admin $admin): ContentVariant
    {
        if ($variant->platform === 'wordpress') {
            throw new RuntimeException('WordPress 主文章不需要平台改写。');
        }

        $variant->loadMissing('sourceArticle.task.aiModel');
        if (! $variant->sourceArticle) {
            throw new RuntimeException('源文章不存在，无法生成平台版本。');
        }

        $rules = $this->platformCatalog->get($variant->platform);
        if ($rules === []) {
            throw new RuntimeException('目标平台规则不存在。');
        }

        $token = (string) Str::uuid();
        DB::transaction(function () use ($variant, $token): void {
            $locked = ContentVariant::query()->whereKey($variant->id)->lockForUpdate()->firstOrFail();
            $leaseIsActive = $locked->status === ContentVariant::STATUS_GENERATING
                && $locked->generation_started_at?->isAfter(now()->subMinutes(15));
            if ($leaseIsActive) {
                throw new RuntimeException('该平台版本正在生成，请勿重复提交。');
            }
            $locked->forceFill([
                'status' => ContentVariant::STATUS_GENERATING,
                'generation_token' => $token,
                'generation_started_at' => now(),
                'failure_message' => null,
            ])->save();
        });

        try {
            $generated = $this->generator->generate($variant->sourceArticle, $variant->platform, $rules);

            $sourceContent = implode("\n", array_filter([
                (string) $variant->sourceArticle->title,
                (string) $variant->sourceArticle->excerpt,
                (string) $variant->sourceArticle->content,
            ]));
            $generatedContent = implode("\n", array_filter([
                (string) $generated['title'],
                (string) $generated['excerpt'],
                (string) $generated['content'],
            ]));
            $factCheck = $this->factChecker->inspect($sourceContent, $generatedContent);
            $qualityCheck = $this->qualityChecker->inspect(
                (string) $generated['title'],
                (string) $generated['content'],
                $rules,
                $factCheck,
                (string) $generated['excerpt'],
                (array) $generated['tags'],
                (array) $generated['image_requirements'],
            );
            $sourceHash = hash('sha256', $sourceContent);

            return DB::transaction(function () use ($variant, $admin, $generated, $rules, $token, $factCheck, $qualityCheck, $sourceHash): ContentVariant {
                $locked = ContentVariant::query()->whereKey($variant->id)->lockForUpdate()->firstOrFail();
                if ($locked->generation_token !== $token || $locked->status !== ContentVariant::STATUS_GENERATING) {
                    throw new RuntimeException('本次生成已被更新的请求替代，结果未保存。');
                }
                $nextVersion = ((int) ContentVariantVersion::query()
                    ->where('content_variant_id', $locked->id)
                    ->max('version')) + 1;
                $meta = [
                    'platform_rules' => $rules,
                    'model' => $generated['model'] ?? null,
                    'source' => $generated['source'] ?? null,
                    'generated_at' => now()->toIso8601String(),
                    'source_content_hash' => $sourceHash,
                    'fact_check' => $factCheck,
                    'quality_check' => $qualityCheck,
                ];

                ContentVariantVersion::query()->create([
                    'content_variant_id' => $locked->id,
                    'version' => $nextVersion,
                    'change_type' => 'generated',
                    'title' => $generated['title'],
                    'excerpt' => $generated['excerpt'],
                    'content' => $generated['content'],
                    'tags' => $generated['tags'],
                    'image_requirements' => $generated['image_requirements'],
                    'template_version' => (string) ($rules['template_version'] ?? '1.0'),
                    'generation_meta' => $meta,
                    'quality_check' => $qualityCheck,
                    'created_by' => $admin->id,
                ]);

                $locked->update([
                    'title' => $generated['title'],
                    'excerpt' => $generated['excerpt'],
                    'content' => $generated['content'],
                    'tags' => $generated['tags'],
                    'image_requirements' => $generated['image_requirements'],
                    'status' => ContentVariant::STATUS_REVIEW_PENDING,
                    'review_status' => ContentVariant::REVIEW_PENDING,
                    'version' => $nextVersion,
                    'template_version' => (string) ($rules['template_version'] ?? '1.0'),
                    'generation_meta' => $meta,
                    'source_content_hash' => $sourceHash,
                    'fact_check' => $factCheck,
                    'quality_check' => $qualityCheck,
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                    'review_note' => null,
                    'generation_token' => null,
                    'generation_started_at' => null,
                    'failure_message' => null,
                ]);

                return $locked->fresh(['versions.creator']);
            });
        } catch (Throwable $exception) {
            ContentVariant::query()
                ->whereKey($variant->id)
                ->where('generation_token', $token)
                ->where('status', ContentVariant::STATUS_GENERATING)
                ->update([
                    'status' => ContentVariant::STATUS_FAILED,
                    'generation_token' => null,
                    'generation_started_at' => null,
                    'failure_message' => mb_substr($exception->getMessage(), 0, 2000),
                    'updated_at' => now(),
                ]);

            throw $exception;
        }
    }
}
