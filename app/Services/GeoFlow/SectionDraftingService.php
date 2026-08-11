<?php

namespace App\Services\GeoFlow;

use App\Contracts\GeoFlow\ContentSectionGenerator;
use App\Enums\ContentDirectionKind;
use App\Enums\ContentProductionStage;
use App\Enums\ContentProductionStatus;
use App\Enums\ContentSectionStatus;
use App\Enums\ContentStageFailureType;
use App\Enums\ContentStageStatus;
use App\Models\Admin;
use App\Models\ContentDirectionVersion;
use App\Models\ContentProduction;
use App\Models\ContentProductionEvent;
use App\Models\ContentSectionVersion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

final class SectionDraftingService
{
    public function __construct(
        private readonly ContentSectionGenerator $generator,
        private readonly ChineseContentGuard $chineseContentGuard,
    ) {}

    /**
     * @return Collection<int, ContentSectionVersion>
     */
    public function initialize(Admin $admin, ContentProduction $production): Collection
    {
        $outline = $this->confirmedOutline($production);
        $candidate = collect($outline->payload['candidates'] ?? [])
            ->firstWhere('id', data_get($outline->payload, 'selected_id'));
        $nodes = collect($candidate['nodes'] ?? []);
        if ($nodes->isEmpty()) {
            throw ValidationException::withMessages(['outline' => '已确认的大纲没有可写作章节。']);
        }

        DB::transaction(function () use ($admin, $production, $outline, $nodes): void {
            ContentProduction::query()->whereKey($production->id)->lockForUpdate()->firstOrFail();
            foreach ($nodes->values() as $position => $node) {
                $exists = ContentSectionVersion::query()
                    ->where('content_production_id', $production->id)
                    ->where('outline_version_id', $outline->id)
                    ->where('section_key', (string) $node['id'])
                    ->exists();
                if ($exists) {
                    continue;
                }

                ContentSectionVersion::query()->create([
                    'content_production_id' => $production->id,
                    'outline_version_id' => $outline->id,
                    'section_key' => (string) $node['id'],
                    'heading' => trim((string) $node['heading']),
                    'level' => (string) $node['level'],
                    'position' => $position + 1,
                    'version' => 1,
                    'status' => ContentSectionStatus::Pending,
                    'evidence_ids' => array_values(array_unique(array_map('intval', $node['evidence_ids'] ?? []))),
                    'input_hash' => $this->inputHash($production, $outline, $node),
                    'prompt_version' => 'section-v1',
                    'generation_source' => 'manual',
                    'created_by_admin_id' => $admin->id,
                ]);
            }

            $this->setStageState($production, ContentStageStatus::WaitingInput);
            $this->audit($production, $admin, 'sections.initialized', ['outline_version_id' => $outline->id]);
        });

        return $this->latestSections($production);
    }

    public function generate(
        Admin $admin,
        ContentProduction $production,
        string $sectionKey,
        bool $force = false,
    ): ContentSectionVersion {
        $current = $this->latestSection($production, $sectionKey);
        if ($current->status === ContentSectionStatus::Succeeded && ! $force) {
            return $current;
        }

        $version = $current;
        if ($force || $current->status === ContentSectionStatus::Failed) {
            $version = $this->newVersion($admin, $current, ContentSectionStatus::Running);
        } else {
            $version->forceFill(['status' => ContentSectionStatus::Running, 'error_message' => null])->save();
        }

        try {
            $result = $this->generator->generate($production->loadMissing('task.aiModel'), $version);
            $this->chineseContentGuard->validateField('content', $result['content']);
            $version->forceFill([
                'status' => ContentSectionStatus::Succeeded,
                'content' => trim($result['content']),
                'model' => $result['model'],
                'generation_source' => $result['source'],
                'error_message' => null,
            ])->save();
        } catch (Throwable $exception) {
            $version->forceFill([
                'status' => ContentSectionStatus::Failed,
                'error_message' => mb_substr($exception->getMessage(), 0, 2000),
            ])->save();
        }

        $this->refreshStageState($production);
        $this->audit($production, $admin, 'section.generated', [
            'section_key' => $sectionKey,
            'section_version_id' => $version->id,
            'status' => $version->status->value,
        ]);

        return $version->refresh();
    }

    public function saveManual(
        Admin $admin,
        ContentProduction $production,
        string $sectionKey,
        string $content,
    ): ContentSectionVersion {
        $this->chineseContentGuard->validateField('content', $content);
        $current = $this->latestSection($production, $sectionKey);
        $version = $this->newVersion($admin, $current, ContentSectionStatus::Succeeded, trim($content));
        $this->refreshStageState($production);
        $this->audit($production, $admin, 'section.manually_rewritten', [
            'section_key' => $sectionKey,
            'section_version_id' => $version->id,
        ]);

        return $version;
    }

    /**
     * @return Collection<int, ContentSectionVersion>
     */
    public function latestSections(ContentProduction $production): Collection
    {
        $outline = $this->confirmedOutline($production);
        $ids = ContentSectionVersion::query()
            ->where('content_production_id', $production->id)
            ->where('outline_version_id', $outline->id)
            ->selectRaw('MAX(id) AS id')
            ->groupBy('section_key')
            ->pluck('id');

        return ContentSectionVersion::query()
            ->whereIn('id', $ids)
            ->orderBy('position')
            ->get();
    }

    private function confirmedOutline(ContentProduction $production): ContentDirectionVersion
    {
        $outline = ContentDirectionVersion::query()
            ->where('content_production_id', $production->id)
            ->where('kind', ContentDirectionKind::Outlines)
            ->whereNotNull('confirmed_at')
            ->whereNull('invalidated_at')
            ->latest('version')
            ->first();
        if (! $outline) {
            throw ValidationException::withMessages(['outline' => '请先选择并确认一个有效大纲。']);
        }

        return $outline;
    }

    private function latestSection(ContentProduction $production, string $sectionKey): ContentSectionVersion
    {
        return ContentSectionVersion::query()
            ->where('content_production_id', $production->id)
            ->where('section_key', $sectionKey)
            ->latest('version')
            ->firstOrFail();
    }

    private function newVersion(
        Admin $admin,
        ContentSectionVersion $current,
        ContentSectionStatus $status,
        ?string $content = null,
    ): ContentSectionVersion {
        return DB::transaction(function () use ($admin, $current, $status, $content): ContentSectionVersion {
            ContentProduction::query()
                ->whereKey($current->content_production_id)
                ->lockForUpdate()
                ->firstOrFail();
            $nextVersion = ((int) ContentSectionVersion::query()
                ->where('content_production_id', $current->content_production_id)
                ->where('section_key', $current->section_key)
                ->max('version')) + 1;

            return ContentSectionVersion::query()->create([
                'content_production_id' => $current->content_production_id,
                'outline_version_id' => $current->outline_version_id,
                'section_key' => $current->section_key,
                'heading' => $current->heading,
                'level' => $current->level,
                'position' => $current->position,
                'version' => $nextVersion,
                'status' => $status,
                'content' => $content,
                'evidence_ids' => $current->evidence_ids,
                'input_hash' => $current->input_hash,
                'prompt_version' => $current->prompt_version,
                'generation_source' => $content === null ? 'laravel_ai_sdk' : 'manual',
                'created_by_admin_id' => $admin->id,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function inputHash(ContentProduction $production, ContentDirectionVersion $outline, array $node): string
    {
        return hash('sha256', json_encode([
            'topic' => $production->topic,
            'outline_version_id' => $outline->id,
            'node' => $node,
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    private function refreshStageState(ContentProduction $production): void
    {
        $sections = $this->latestSections($production);
        $status = match (true) {
            $sections->contains(fn (ContentSectionVersion $section): bool => $section->status === ContentSectionStatus::Failed) => ContentStageStatus::Failed,
            $sections->every(fn (ContentSectionVersion $section): bool => $section->status === ContentSectionStatus::Succeeded) => ContentStageStatus::Succeeded,
            default => ContentStageStatus::WaitingInput,
        };

        $this->setStageState($production, $status);
    }

    private function setStageState(ContentProduction $production, ContentStageStatus $status): void
    {
        $sections = $this->latestSections($production);
        $run = $production->stageRuns()
            ->where('stage', ContentProductionStage::SectionWriting->value)
            ->latest('attempt')
            ->first();
        $run?->forceFill([
            'status' => $status,
            'output_payload' => ['section_version_ids' => $sections->pluck('id')->all()],
            'failure_type' => $status === ContentStageStatus::Failed ? ContentStageFailureType::ProviderUnavailable : null,
            'error_message' => $status === ContentStageStatus::Failed ? '至少一个章节生成失败，可单独重试。' : null,
            'finished_at' => $status === ContentStageStatus::Succeeded ? now() : null,
            'invalidated_at' => null,
            'invalidation_reason' => null,
        ])->save();

        $production->forceFill([
            'status' => $status === ContentStageStatus::Failed
                ? ContentProductionStatus::Failed
                : ContentProductionStatus::WaitingInput,
            'current_stage' => $status === ContentStageStatus::Succeeded
                ? ContentProductionStage::Assembly
                : ContentProductionStage::SectionWriting,
            'last_error_message' => $status === ContentStageStatus::Failed
                ? '至少一个章节生成失败，可单独重试。'
                : null,
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function audit(ContentProduction $production, Admin $admin, string $event, array $metadata): void
    {
        ContentProductionEvent::query()->create([
            'content_production_id' => $production->id,
            'admin_id' => $admin->id,
            'event' => $event,
            'metadata' => $metadata,
        ]);
    }
}
