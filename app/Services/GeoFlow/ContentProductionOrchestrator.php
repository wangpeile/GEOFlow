<?php

namespace App\Services\GeoFlow;

use App\Enums\ContentProductionMode;
use App\Enums\ContentProductionStatus;
use App\Enums\ContentStageStatus;
use App\Models\Admin;
use App\Models\ContentProduction;
use App\Models\ContentStageRun;
use App\Models\WritingRule;
use App\Models\WritingRuleVersion;
use App\Support\GeoFlow\ContentProduction\ContentProductionWorkflow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ContentProductionOrchestrator
{
    public function __construct(
        private readonly ContentProductionWorkflow $workflow,
        private readonly WritingRuleResolver $writingRules,
    ) {}

    /**
     * @param  array{name: string, topic: string, mode: string, language: string, target_platforms?: list<string>, writing_rule_id?: int|null, writing_rule_version_id?: int|null, task_id?: int|null, idempotency_key?: string|null, context?:array<string,mixed>}  $attributes
     */
    public function create(Admin $admin, array $attributes): ContentProduction
    {
        return DB::transaction(function () use ($admin, $attributes): ContentProduction {
            $idempotencyKey = $attributes['idempotency_key'] ?? null;

            if ($idempotencyKey) {
                $existing = ContentProduction::query()
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    return $existing;
                }
            }

            $rule = isset($attributes['writing_rule_id'])
                ? WritingRule::query()->findOrFail($attributes['writing_rule_id'])
                : null;
            $specifiedVersion = isset($attributes['writing_rule_version_id'])
                ? WritingRuleVersion::query()->findOrFail($attributes['writing_rule_version_id'])
                : null;
            $ruleSnapshot = $rule
                ? ($specifiedVersion
                    ? $this->writingRules->snapshotVersion($rule, $specifiedVersion)
                    : $this->writingRules->snapshot($rule))
                : null;

            $productionAttributes = [
                'uuid' => (string) Str::uuid(),
                'idempotency_key' => $idempotencyKey,
                'created_by_admin_id' => $admin->getKey(),
                'task_id' => $attributes['task_id'] ?? null,
                'name' => $attributes['name'],
                'topic' => $attributes['topic'],
                'mode' => ContentProductionMode::from($attributes['mode']),
                'status' => ContentProductionStatus::Draft,
                'current_stage' => $this->workflow->definitions()[0]->stage,
                'language' => $attributes['language'],
                'target_platforms' => $attributes['target_platforms'] ?? ['wordpress'],
                'context' => array_replace([
                    'topic' => $attributes['topic'],
                    'language' => $attributes['language'],
                    'writing_rule' => $ruleSnapshot,
                    'category_id' => $attributes['category_id'] ?? null,
                    'author_id' => $attributes['author_id'] ?? null,
                ], $attributes['context'] ?? []),
            ];

            // Supports rolling deploys and isolated service tests while the optional
            // writing-rule snapshot migration is not present yet.
            if (Schema::hasColumn('content_productions', 'writing_rule_id')) {
                $productionAttributes['writing_rule_id'] = $ruleSnapshot['writing_rule_id'] ?? null;
                $productionAttributes['writing_rule_version_id'] = $ruleSnapshot['writing_rule_version_id'] ?? null;
                $productionAttributes['writing_rule_snapshot'] = $ruleSnapshot;
            }

            $production = ContentProduction::query()->create($productionAttributes);

            foreach ($this->workflow->definitions() as $sequence => $definition) {
                $production->stageRuns()->create([
                    'stage' => $definition->stage,
                    'status' => ContentStageStatus::Pending,
                    'sequence' => $sequence + 1,
                    'attempt' => 1,
                    'contract_version' => 1,
                ]);
            }

            $production->events()->create([
                'admin_id' => $admin->getKey(),
                'event' => 'production_created',
                'to_status' => ContentProductionStatus::Draft->value,
                'metadata' => [
                    'stage_count' => count($this->workflow->definitions()),
                    'writing_rule_version_id' => $ruleSnapshot['writing_rule_version_id'] ?? null,
                ],
            ]);

            return $production->load('stageRuns');
        });
    }

    public function retry(Admin $admin, ContentProduction $production, ContentStageRun $stageRun): ContentStageRun
    {
        return DB::transaction(function () use ($admin, $production, $stageRun): ContentStageRun {
            $lockedProduction = ContentProduction::query()->lockForUpdate()->findOrFail($production->getKey());
            $lockedRun = ContentStageRun::query()
                ->whereBelongsTo($lockedProduction)
                ->lockForUpdate()
                ->findOrFail($stageRun->getKey());

            if ($lockedRun->status !== ContentStageStatus::Failed) {
                throw ValidationException::withMessages([
                    'stage' => '只有失败的阶段可以重试。',
                ]);
            }

            $existingRetry = ContentStageRun::query()
                ->whereBelongsTo($lockedProduction)
                ->where('stage', $lockedRun->stage->value)
                ->where('attempt', '>', $lockedRun->attempt)
                ->whereIn('status', [
                    ContentStageStatus::Pending->value,
                    ContentStageStatus::Running->value,
                    ContentStageStatus::WaitingInput->value,
                ])
                ->latest('attempt')
                ->first();

            if ($existingRetry) {
                return $existingRetry;
            }

            $attempt = (int) ContentStageRun::query()
                ->whereBelongsTo($lockedProduction)
                ->where('stage', $lockedRun->stage->value)
                ->max('attempt') + 1;

            $retry = $lockedProduction->stageRuns()->create([
                'stage' => $lockedRun->stage,
                'status' => ContentStageStatus::Pending,
                'sequence' => $lockedRun->sequence,
                'attempt' => $attempt,
                'contract_version' => $lockedRun->contract_version,
                'input_hash' => $lockedRun->input_hash,
                'input_payload' => $lockedRun->input_payload,
                'model' => $lockedRun->model,
                'rule_version' => $lockedRun->rule_version,
            ]);

            $lockedProduction->update([
                'status' => ContentProductionStatus::Queued,
                'current_stage' => $lockedRun->stage,
                'failure_type' => null,
                'last_error_message' => null,
            ]);

            $lockedProduction->events()->create([
                'content_stage_run_id' => $retry->getKey(),
                'admin_id' => $admin->getKey(),
                'event' => 'stage_retry_queued',
                'from_status' => ContentProductionStatus::Failed->value,
                'to_status' => ContentProductionStatus::Queued->value,
                'metadata' => [
                    'stage' => $lockedRun->stage->value,
                    'attempt' => $attempt,
                    'previous_stage_run_id' => $lockedRun->getKey(),
                ],
            ]);

            return $retry;
        });
    }
}
