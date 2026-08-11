<?php

namespace App\Services\GeoFlow;

use App\Enums\ContentDirectionKind;
use App\Enums\ContentEvidenceUsage;
use App\Enums\ContentProductionMode;
use App\Enums\ContentProductionStage;
use App\Enums\ContentProductionStatus;
use App\Enums\ContentStageStatus;
use App\Models\Admin;
use App\Models\ContentDirectionVersion;
use App\Models\ContentProduction;
use App\Models\ContentProductionEvent;
use App\Models\ContentStageRun;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ContentDirectionService
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function saveBrief(Admin $admin, ContentProduction $production, array $attributes): ContentDirectionVersion
    {
        $evidenceIds = $production->evidences()
            ->where('usage', '!=', ContentEvidenceUsage::Disabled->value)
            ->whereIn('id', $attributes['evidence_ids'] ?? [])
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
        $requestedEvidenceIds = array_values(array_unique(array_map('intval', $attributes['evidence_ids'] ?? [])));
        if ($requestedEvidenceIds !== [] && count($evidenceIds) !== count($requestedEvidenceIds)) {
            throw ValidationException::withMessages([
                'evidence_ids' => '所选证据包含已停用或不属于当前项目的记录，请刷新后重试。',
            ]);
        }
        if ($evidenceIds === []) {
            throw ValidationException::withMessages([
                'evidence_ids' => '内容简报至少需要关联一条可用证据。',
            ]);
        }

        $payload = [
            'article_type' => trim((string) $attributes['article_type']),
            'target_audience' => trim((string) $attributes['target_audience']),
            'search_intent' => trim((string) $attributes['search_intent']),
            'content_angle' => trim((string) $attributes['content_angle']),
            'must_cover' => $this->lines((string) ($attributes['must_cover'] ?? '')),
            'avoid_topics' => $this->lines((string) ($attributes['avoid_topics'] ?? '')),
            'evidence_ids' => $evidenceIds,
            'language' => 'zh_CN',
            'generation_source' => (string) ($attributes['generation_source'] ?? 'manual'),
        ];

        return $this->createVersion(
            $admin,
            $production,
            ContentDirectionKind::Brief,
            $payload,
            [],
            [ContentDirectionKind::Titles, ContentDirectionKind::Outlines],
            '内容简报已修改',
        );
    }

    public function generateBrief(Admin $admin, ContentProduction $production): ContentDirectionVersion
    {
        $evidences = $production->evidences()
            ->where('usage', '!=', ContentEvidenceUsage::Disabled->value)
            ->oldest('id')
            ->get();
        if ($evidences->isEmpty()) {
            throw ValidationException::withMessages([
                'brief' => '请先添加并启用至少一条证据，再生成内容简报。',
            ]);
        }

        $mustCover = $evidences
            ->pluck('source_title')
            ->filter()
            ->unique()
            ->take(8)
            ->values()
            ->all();

        return $this->saveBrief($admin, $production, [
            'article_type' => '深度博客文章',
            'target_audience' => '正在了解或评估“'.$production->topic.'”的中文读者',
            'search_intent' => '理解主题、比较方案并获得可执行建议',
            'content_angle' => '以可核验资料为依据，从问题、方法、选择标准和行动建议展开',
            'must_cover' => implode("\n", $mustCover),
            'avoid_topics' => "无来源支撑的数字和结论\n无法核验的绝对化承诺",
            'evidence_ids' => $evidences->pluck('id')->all(),
            'generation_source' => 'local_fallback',
        ]);
    }

    public function generateTitles(Admin $admin, ContentProduction $production): ContentDirectionVersion
    {
        $brief = $this->sourceForNextStep($production, ContentDirectionKind::Brief);
        $this->ensureUsable($brief, '请先保存内容简报。');

        $topic = trim($production->topic);
        $angle = trim((string) data_get($brief->payload, 'content_angle'));
        $candidates = [
            ['id' => (string) Str::uuid(), 'title' => $topic.'完整指南：从关键问题到行动方案', 'rationale' => '覆盖认知与行动意图'],
            ['id' => (string) Str::uuid(), 'title' => '如何做好'.$topic.'？方法、标准与常见误区', 'rationale' => '适合问题解决型检索'],
            ['id' => (string) Str::uuid(), 'title' => $topic.'深度解析：'.$this->shorten($angle, 22), 'rationale' => '突出已确认的内容角度'],
            ['id' => (string) Str::uuid(), 'title' => '选择'.$topic.'方案前，需要看懂的核心要点', 'rationale' => '适合评估和比较意图'],
            ['id' => (string) Str::uuid(), 'title' => $topic.'实践指南：可信资料、步骤与检查清单', 'rationale' => '强调证据和实操价值'],
        ];

        return $this->createVersion(
            $admin,
            $production,
            ContentDirectionKind::Titles,
            [
                'candidates' => $candidates,
                'selected_id' => null,
                'selected_title' => null,
                'language' => 'zh_CN',
                'generation_source' => 'local_fallback',
            ],
            [$brief->id],
            [ContentDirectionKind::Outlines],
            '标题候选已重新生成',
        );
    }

    public function selectTitle(
        Admin $admin,
        ContentProduction $production,
        ?string $candidateId,
        ?string $customTitle,
    ): ContentDirectionVersion {
        $current = $this->latestUsable($production, ContentDirectionKind::Titles);
        $this->ensureUsable($current, '请先生成标题候选。');

        $title = trim((string) $customTitle);
        if ($title === '') {
            $candidate = collect($current->payload['candidates'] ?? [])
                ->firstWhere('id', $candidateId);
            $title = trim((string) ($candidate['title'] ?? ''));
        }
        if ($title === '') {
            throw ValidationException::withMessages(['title' => '请选择一个标题或填写自定义标题。']);
        }

        $payload = $current->payload;
        $payload['selected_id'] = $customTitle ? null : $candidateId;
        $payload['selected_title'] = $title;

        return $this->createVersion(
            $admin,
            $production,
            ContentDirectionKind::Titles,
            $payload,
            [$current->id],
            [ContentDirectionKind::Outlines],
            '已选择的标题发生变化',
        );
    }

    public function generateOutlines(Admin $admin, ContentProduction $production): ContentDirectionVersion
    {
        $brief = $this->sourceForNextStep($production, ContentDirectionKind::Brief);
        $titles = $this->sourceForNextStep($production, ContentDirectionKind::Titles);
        $this->ensureUsable($brief, '请先保存内容简报。');
        $this->ensureUsable($titles, '请先选择标题。');

        $selectedTitle = trim((string) data_get($titles->payload, 'selected_title'));
        if ($selectedTitle === '') {
            throw ValidationException::withMessages(['outline' => '请先选择或填写标题。']);
        }

        $mustCover = collect($brief->payload['must_cover'] ?? [])->filter()->values();
        $evidenceIds = collect($brief->payload['evidence_ids'] ?? [])->map(
            static fn (mixed $id): int => (int) $id
        )->all();

        $candidateOne = $this->outlineCandidate('问题解决型', [
            ['h2', '为什么需要关注'.$production->topic],
            ['h2', $production->topic.'的核心概念与判断标准'],
            ['h3', '关键概念'],
            ['h3', '评估时容易忽略的因素'],
            ['h2', '可执行的方法与步骤'],
            ['h2', '常见误区及修正建议'],
            ['h2', '下一步行动清单'],
        ], $evidenceIds);

        $coverageHeadings = $mustCover->take(4)->map(
            static fn (mixed $item): array => ['h2', trim((string) $item)]
        )->all();
        $candidateTwo = $this->outlineCandidate('证据驱动型', array_merge([
            ['h2', $selectedTitle.'：先看结论'],
            ['h2', '已有资料揭示了什么'],
        ], $coverageHeadings, [
            ['h2', '方案比较与适用场景'],
            ['h2', '实施建议与风险提示'],
            ['h2', '总结'],
        ]), $evidenceIds);

        return $this->createVersion(
            $admin,
            $production,
            ContentDirectionKind::Outlines,
            [
                'title' => $selectedTitle,
                'candidates' => [$candidateOne, $candidateTwo],
                'selected_id' => null,
                'language' => 'zh_CN',
                'generation_source' => 'local_fallback',
            ],
            [$brief->id, $titles->id],
            [],
            '',
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateOutline(Admin $admin, ContentProduction $production, array $attributes): ContentDirectionVersion
    {
        $current = $this->latestUsable($production, ContentDirectionKind::Outlines);
        $this->ensureUsable($current, '请先生成大纲候选。');

        $payload = $current->payload;
        $candidateId = (string) $attributes['candidate_id'];
        $action = (string) $attributes['action'];
        $nodeId = (string) ($attributes['node_id'] ?? '');

        $candidateMatched = false;
        foreach ($payload['candidates'] as &$candidate) {
            if (($candidate['id'] ?? '') !== $candidateId) {
                continue;
            }
            $candidateMatched = true;
            if ($action === 'select') {
                $payload['selected_id'] = $candidateId;

                continue;
            }

            $index = collect($candidate['nodes'])->search(
                static fn (array $node): bool => ($node['id'] ?? '') === $nodeId
            );
            if ($index === false) {
                throw ValidationException::withMessages(['outline' => '未找到需要编辑的大纲节点。']);
            }

            if ($action === 'delete') {
                array_splice($candidate['nodes'], $index, 1);
            } elseif ($action === 'up' && $index > 0) {
                [$candidate['nodes'][$index - 1], $candidate['nodes'][$index]] = [
                    $candidate['nodes'][$index],
                    $candidate['nodes'][$index - 1],
                ];
            } elseif ($action === 'down' && $index < count($candidate['nodes']) - 1) {
                [$candidate['nodes'][$index + 1], $candidate['nodes'][$index]] = [
                    $candidate['nodes'][$index],
                    $candidate['nodes'][$index + 1],
                ];
            } elseif ($action === 'edit') {
                $candidate['nodes'][$index]['heading'] = trim((string) $attributes['heading']);
                $candidate['nodes'][$index]['level'] = (string) $attributes['level'];
            } elseif ($action === 'regenerate') {
                $candidate['nodes'][$index]['heading'] = $this->regeneratedHeading(
                    (string) $candidate['nodes'][$index]['heading']
                );
            }
        }
        unset($candidate);
        if (! $candidateMatched) {
            throw ValidationException::withMessages(['outline' => '未找到需要操作的大纲候选。']);
        }
        $this->validateOutlinePayload($payload);

        return $this->createVersion(
            $admin,
            $production,
            ContentDirectionKind::Outlines,
            $payload,
            [$current->id],
            [],
            '',
        );
    }

    public function confirm(
        Admin $admin,
        ContentProduction $production,
        ContentDirectionKind $kind,
    ): ContentDirectionVersion {
        return DB::transaction(function () use ($admin, $production, $kind): ContentDirectionVersion {
            ContentProduction::query()->whereKey($production->id)->lockForUpdate()->firstOrFail();
            $version = ContentDirectionVersion::query()
                ->where('content_production_id', $production->id)
                ->where('kind', $kind)
                ->whereNull('invalidated_at')
                ->lockForUpdate()
                ->latest('version')
                ->firstOrFail();

            if ($kind === ContentDirectionKind::Titles && blank(data_get($version->payload, 'selected_title'))) {
                throw ValidationException::withMessages(['title' => '请先选择一个标题。']);
            }
            if ($kind === ContentDirectionKind::Outlines && blank(data_get($version->payload, 'selected_id'))) {
                throw ValidationException::withMessages(['outline' => '请先选择一个大纲。']);
            }

            $version->forceFill([
                'confirmed_by_admin_id' => $admin->id,
                'confirmed_at' => now(),
            ])->save();
            $this->markStageConfirmed($production, $kind, $version);

            $this->audit($production, $admin, 'direction.confirmed', [
                'kind' => $kind->value,
                'version_id' => $version->id,
                'version' => $version->version,
            ]);

            return $version->refresh();
        });
    }

    private function createVersion(
        Admin $admin,
        ContentProduction $production,
        ContentDirectionKind $kind,
        array $payload,
        array $sourceVersionIds,
        array $invalidateKinds,
        string $reason,
    ): ContentDirectionVersion {
        return DB::transaction(function () use (
            $admin,
            $production,
            $kind,
            $payload,
            $sourceVersionIds,
            $invalidateKinds,
            $reason
        ): ContentDirectionVersion {
            ContentProduction::query()->whereKey($production->id)->lockForUpdate()->firstOrFail();
            $nextVersion = ((int) ContentDirectionVersion::query()
                ->where('content_production_id', $production->id)
                ->where('kind', $kind)
                ->max('version')) + 1;

            $version = ContentDirectionVersion::query()->create([
                'content_production_id' => $production->id,
                'kind' => $kind,
                'version' => $nextVersion,
                'payload' => $payload,
                'input_hash' => hash('sha256', json_encode([
                    'topic' => $production->topic,
                    'payload' => $payload,
                    'sources' => $sourceVersionIds,
                ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
                'source_version_ids' => $sourceVersionIds,
                'created_by_admin_id' => $admin->id,
            ]);

            if ($invalidateKinds !== []) {
                ContentDirectionVersion::query()
                    ->where('content_production_id', $production->id)
                    ->whereIn('kind', array_map(
                        static fn (ContentDirectionKind $item): string => $item->value,
                        $invalidateKinds
                    ))
                    ->whereNull('invalidated_at')
                    ->update(['invalidated_at' => now(), 'invalidation_reason' => $reason]);

            }
            $this->invalidateStageRuns($production, $kind, $reason ?: '内容方向版本发生变化');
            $this->markStageWaitingForConfirmation($production, $kind, $version);

            $this->audit($production, $admin, 'direction.version_created', [
                'kind' => $kind->value,
                'version_id' => $version->id,
                'version' => $version->version,
            ]);

            return $version;
        });
    }

    private function invalidateStageRuns(
        ContentProduction $production,
        ContentDirectionKind $changedKind,
        string $reason,
    ): void {
        $stages = match ($changedKind) {
            ContentDirectionKind::Brief => [
                ContentProductionStage::Title,
                ContentProductionStage::Outline,
                ContentProductionStage::SectionWriting,
                ContentProductionStage::Assembly,
                ContentProductionStage::QualityGate,
                ContentProductionStage::TargetedRepair,
                ContentProductionStage::Review,
                ContentProductionStage::WordPressPublish,
                ContentProductionStage::PlatformRewrite,
            ],
            ContentDirectionKind::Titles => [
                ContentProductionStage::Outline,
                ContentProductionStage::SectionWriting,
                ContentProductionStage::Assembly,
                ContentProductionStage::QualityGate,
                ContentProductionStage::TargetedRepair,
                ContentProductionStage::Review,
                ContentProductionStage::WordPressPublish,
                ContentProductionStage::PlatformRewrite,
            ],
            ContentDirectionKind::Outlines => [
                ContentProductionStage::SectionWriting,
                ContentProductionStage::Assembly,
                ContentProductionStage::QualityGate,
                ContentProductionStage::TargetedRepair,
                ContentProductionStage::Review,
                ContentProductionStage::WordPressPublish,
                ContentProductionStage::PlatformRewrite,
            ],
        };

        ContentStageRun::query()
            ->where('content_production_id', $production->id)
            ->whereIn('stage', array_map(
                static fn (ContentProductionStage $stage): string => $stage->value,
                $stages
            ))
            ->whereNull('invalidated_at')
            ->update(['invalidated_at' => now(), 'invalidation_reason' => $reason]);
    }

    private function latestUsable(
        ContentProduction $production,
        ContentDirectionKind $kind,
    ): ?ContentDirectionVersion {
        return ContentDirectionVersion::query()
            ->where('content_production_id', $production->id)
            ->where('kind', $kind)
            ->whereNull('invalidated_at')
            ->latest('version')
            ->first();
    }

    private function sourceForNextStep(
        ContentProduction $production,
        ContentDirectionKind $kind,
    ): ?ContentDirectionVersion {
        $query = ContentDirectionVersion::query()
            ->where('content_production_id', $production->id)
            ->where('kind', $kind)
            ->whereNull('invalidated_at');

        if ($production->mode === ContentProductionMode::Guided) {
            $query->whereNotNull('confirmed_at');
        }

        return $query->latest('version')->first();
    }

    private function markStageWaitingForConfirmation(
        ContentProduction $production,
        ContentDirectionKind $kind,
        ContentDirectionVersion $version,
    ): void {
        $stage = $this->stageForKind($kind);
        $status = $production->mode === ContentProductionMode::Guided
            ? ContentStageStatus::WaitingInput
            : ContentStageStatus::Succeeded;

        ContentStageRun::query()
            ->where('content_production_id', $production->id)
            ->where('stage', $stage->value)
            ->latest('attempt')
            ->limit(1)
            ->update([
                'status' => $status->value,
                'output_payload' => ['direction_version_id' => $version->id],
                'finished_at' => $status === ContentStageStatus::Succeeded ? now() : null,
                'invalidated_at' => null,
                'invalidation_reason' => null,
            ]);

        if ($production->mode === ContentProductionMode::Guided) {
            $production->forceFill([
                'status' => ContentProductionStatus::WaitingInput,
                'current_stage' => $stage,
            ])->save();
        }
    }

    private function markStageConfirmed(
        ContentProduction $production,
        ContentDirectionKind $kind,
        ContentDirectionVersion $version,
    ): void {
        $stage = $this->stageForKind($kind);
        ContentStageRun::query()
            ->where('content_production_id', $production->id)
            ->where('stage', $stage->value)
            ->latest('attempt')
            ->limit(1)
            ->update([
                'status' => ContentStageStatus::Succeeded->value,
                'output_payload' => ['direction_version_id' => $version->id],
                'finished_at' => now(),
                'invalidated_at' => null,
                'invalidation_reason' => null,
            ]);

        if ($production->mode === ContentProductionMode::Guided) {
            $production->forceFill([
                'status' => ContentProductionStatus::WaitingInput,
                'current_stage' => match ($kind) {
                    ContentDirectionKind::Brief => ContentProductionStage::Title,
                    ContentDirectionKind::Titles => ContentProductionStage::Outline,
                    ContentDirectionKind::Outlines => ContentProductionStage::SectionWriting,
                },
            ])->save();
        }
    }

    private function stageForKind(ContentDirectionKind $kind): ContentProductionStage
    {
        return match ($kind) {
            ContentDirectionKind::Brief => ContentProductionStage::Brief,
            ContentDirectionKind::Titles => ContentProductionStage::Title,
            ContentDirectionKind::Outlines => ContentProductionStage::Outline,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function validateOutlinePayload(array $payload): void
    {
        foreach ($payload['candidates'] ?? [] as $candidate) {
            $nodes = $candidate['nodes'] ?? [];
            if ($nodes === [] || ($nodes[0]['level'] ?? null) !== 'h2') {
                throw ValidationException::withMessages(['outline' => '每个大纲必须以 H2 开始。']);
            }
            $hasH2 = false;
            foreach ($nodes as $node) {
                if (($node['level'] ?? null) === 'h2') {
                    $hasH2 = true;
                } elseif (($node['level'] ?? null) === 'h3' && ! $hasH2) {
                    throw ValidationException::withMessages(['outline' => 'H3 必须归属于前面的 H2。']);
                }
            }
        }
    }

    private function ensureUsable(?ContentDirectionVersion $version, string $message): void
    {
        if ($version === null) {
            throw ValidationException::withMessages(['direction' => $message]);
        }
    }

    /**
     * @return list<string>
     */
    private function lines(string $value): array
    {
        return collect(preg_split('/\R/u', $value) ?: [])
            ->map(static fn (string $line): string => trim($line))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<array{0:string,1:string}>  $headings
     * @param  list<int>  $evidenceIds
     * @return array<string, mixed>
     */
    private function outlineCandidate(string $name, array $headings, array $evidenceIds): array
    {
        return [
            'id' => (string) Str::uuid(),
            'name' => $name,
            'nodes' => array_map(static fn (array $item): array => [
                'id' => (string) Str::uuid(),
                'level' => $item[0],
                'heading' => $item[1],
                'evidence_ids' => $evidenceIds,
            ], $headings),
        ];
    }

    private function shorten(string $value, int $length): string
    {
        return rtrim(Str::limit($value, $length, ''), '，。；、 ');
    }

    private function regeneratedHeading(string $heading): string
    {
        return rtrim($heading, '？?。').'：关键判断与实践建议';
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
