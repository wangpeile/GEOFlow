<?php

namespace App\Services\GeoFlow;

use App\Enums\ContentDirectionKind;
use App\Enums\ContentProductionMode;
use App\Models\ContentDirectionVersion;
use App\Models\ContentProduction;
use App\Models\ContentTopicIdea;
use App\Models\TaskSchedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class StandardContentProductionRunner
{
    public function __construct(
        private readonly ContentProductionOrchestrator $orchestrator,
        private readonly ContentEvidenceService $evidence,
        private readonly ContentDirectionService $directions,
        private readonly SectionDraftingService $sections,
        private readonly ArticleAssemblyService $assembly,
        private readonly QualityGateService $quality,
    ) {}

    public function run(TaskSchedule $schedule): ContentProduction
    {
        $schedule->loadMissing(['task.createdByAdmin', 'task.knowledgeBases', 'task.knowledgeBase']);
        $task = $schedule->task;
        $admin = $task->createdByAdmin;
        if (! $admin) {
            throw ValidationException::withMessages(['task' => '自动任务缺少执行责任人。']);
        }

        $production = $this->orchestrator->create($admin, [
            'task_id' => $task->id,
            'name' => $task->name.' - '.$schedule->topic,
            'topic' => $schedule->topic,
            'mode' => ContentProductionMode::Standard->value,
            'language' => 'zh_CN',
            'target_platforms' => data_get($task->automation_settings, 'target_platforms', ['wordpress']),
            'writing_rule_id' => $task->writing_rule_id,
            'writing_rule_version_id' => $task->writing_rule_version_id,
            'content_topic_id' => data_get($schedule->metadata, 'content_topic_id'),
            'content_topic_idea_id' => data_get($schedule->metadata, 'content_topic_idea_id'),
            'idempotency_key' => hash('sha256', 'task-schedule:'.$schedule->id.':'.$schedule->topic_hash),
            'context' => [
                'automation' => ['task_schedule_id' => $schedule->id, 'output_policy' => $task->production_output_policy, 'production_plan_id' => $task->id],
            ],
        ]);

        $this->linkProduction($schedule, $production);
        if ($production->content_topic_idea_id) {
            ContentTopicIdea::query()->whereKey($production->content_topic_idea_id)->update(['status' => 'generating']);
        }

        $knowledgeBase = $task->knowledgeBases->first() ?? $task->knowledgeBase;
        if (! $knowledgeBase) {
            throw ValidationException::withMessages(['knowledge_base' => '标准自动任务必须关联至少一个知识库。']);
        }
        if (! $production->evidences()->exists()) {
            $this->evidence->retrieveKnowledge($admin, $production, $knowledgeBase, $schedule->topic, 5);
        }

        if (! $this->latestDirection($production, ContentDirectionKind::Brief)) {
            $this->directions->generateBrief($admin, $production);
        }

        $titles = $this->latestDirection($production, ContentDirectionKind::Titles, true);
        if (! $titles) {
            $titles = $this->latestDirection($production, ContentDirectionKind::Titles)
                ?? $this->directions->generateTitles($admin, $production);
            if (blank(data_get($titles->payload, 'selected_title'))) {
                $titleId = data_get($titles->payload, 'candidates.0.id');
                $this->directions->selectTitle($admin, $production, $titleId, null);
            }
            $titles = $this->directions->confirm($admin, $production, ContentDirectionKind::Titles);
        }

        $outlines = $this->latestDirection($production, ContentDirectionKind::Outlines, true);
        if (! $outlines) {
            $outlines = $this->latestDirection($production, ContentDirectionKind::Outlines)
                ?? $this->directions->generateOutlines($admin, $production);
            if (blank(data_get($outlines->payload, 'selected_id'))) {
                $outlineId = data_get($outlines->payload, 'candidates.0.id');
                $this->directions->updateOutline($admin, $production, ['candidate_id' => $outlineId, 'action' => 'select']);
            }
            $this->directions->confirm($admin, $production, ContentDirectionKind::Outlines);
        }

        foreach ($this->sections->initialize($admin, $production) as $section) {
            $result = $this->sections->generate($admin, $production, $section->section_key);
            if ($result->status->value !== 'succeeded') {
                throw ValidationException::withMessages(['section' => $result->error_message ?: '章节生成失败。']);
            }
        }

        $articleVersion = $this->assembly->assemble($admin, $production);
        $this->quality->inspect($admin, $production, $articleVersion);

        return $production->refresh();
    }

    private function linkProduction(TaskSchedule $schedule, ContentProduction $production): void
    {
        DB::transaction(function () use ($schedule, $production): void {
            $locked = TaskSchedule::query()->lockForUpdate()->findOrFail($schedule->id);
            if ($locked->content_production_id && $locked->content_production_id !== $production->id) {
                throw ValidationException::withMessages(['schedule' => '该运行记录已关联到其他内容生产项目。']);
            }
            $locked->forceFill(['content_production_id' => $production->id])->save();
        });
    }

    private function latestDirection(
        ContentProduction $production,
        ContentDirectionKind $kind,
        bool $confirmed = false,
    ): ?ContentDirectionVersion {
        return ContentDirectionVersion::query()
            ->where('content_production_id', $production->id)
            ->where('kind', $kind)
            ->whereNull('invalidated_at')
            ->when($confirmed, fn ($query) => $query->whereNotNull('confirmed_at'))
            ->latest('version')
            ->first();
    }
}
