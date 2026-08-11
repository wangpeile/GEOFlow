<?php

namespace App\Services\GeoFlow;

use App\Enums\ArticleVersionKind;
use App\Enums\ContentProductionStage;
use App\Enums\ContentProductionStatus;
use App\Enums\ContentStageStatus;
use App\Models\Admin;
use App\Models\Article;
use App\Models\ArticleVersion;
use App\Models\ContentProduction;
use App\Models\ContentProductionEvent;
use App\Models\ContentStageRun;
use App\Models\QualityRepairAttempt;
use App\Models\QualityReport;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

final class TargetedRepairService
{
    public const MAX_ATTEMPTS = 3;

    public function __construct(private readonly QualityGateService $qualityGateService) {}

    /**
     * @param  list<string>  $selectedIssueIds
     * @return array{attempt: QualityRepairAttempt, article_version: ArticleVersion, quality_report: QualityReport}
     */
    public function repair(
        Admin $admin,
        ContentProduction $production,
        QualityReport $report,
        array $selectedIssueIds,
    ): array {
        $this->validateRequest($production, $report, $selectedIssueIds);
        $issues = collect($report->issues)
            ->whereIn('id', $selectedIssueIds)
            ->where('repairable', true)
            ->values();
        if ($issues->count() !== count(array_unique($selectedIssueIds))) {
            throw ValidationException::withMessages([
                'issue_ids' => '所选问题包含不存在或不能自动修复的项目。',
            ]);
        }

        $attempt = DB::transaction(function () use ($admin, $production, $report, $selectedIssueIds): QualityRepairAttempt {
            ContentProduction::query()->lockForUpdate()->findOrFail($production->id);
            $attemptNumber = ((int) QualityRepairAttempt::query()
                ->whereBelongsTo($report)
                ->max('attempt')) + 1;
            if ($attemptNumber > self::MAX_ATTEMPTS) {
                throw ValidationException::withMessages([
                    'issue_ids' => '当前质量报告的自动修复已达到 3 次上限，请转人工处理。',
                ]);
            }

            return QualityRepairAttempt::query()->create([
                'content_production_id' => $production->id,
                'quality_report_id' => $report->id,
                'source_article_version_id' => $report->article_version_id,
                'attempt' => $attemptNumber,
                'status' => 'running',
                'selected_issue_ids' => array_values(array_unique($selectedIssueIds)),
                'created_by_admin_id' => $admin->id,
            ]);
        });

        try {
            $source = $report->articleVersion()->firstOrFail();
            [$title, $summary, $body, $faq, $metaTitle, $metaDescription, $changes] =
                $this->applyRepairs($production, $source, $issues->all());
            if ($changes === []) {
                throw ValidationException::withMessages([
                    'issue_ids' => '所选问题没有产生可保存的修改。',
                ]);
            }

            $version = DB::transaction(function () use (
                $admin,
                $production,
                $report,
                $source,
                $attempt,
                $title,
                $summary,
                $body,
                $faq,
                $metaTitle,
                $metaDescription,
                $changes,
            ): ArticleVersion {
                $lockedProduction = ContentProduction::query()->lockForUpdate()->findOrFail($production->id);
                $lockedAttempt = QualityRepairAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
                $inputHash = hash('sha256', json_encode([
                    'source_article_version_id' => $source->id,
                    'quality_report_id' => $report->id,
                    'selected_issue_ids' => $lockedAttempt->selected_issue_ids,
                    'repair_rule' => 'targeted-repair-v1',
                ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
                $version = ArticleVersion::query()
                    ->where('content_production_id', $lockedProduction->id)
                    ->where('input_hash', $inputHash)
                    ->first();
                if (! $version) {
                    $version = ArticleVersion::query()->create([
                        'content_production_id' => $lockedProduction->id,
                        'article_id' => $source->article_id,
                        'version' => ((int) ArticleVersion::query()
                            ->where('content_production_id', $lockedProduction->id)
                            ->max('version')) + 1,
                        'kind' => ArticleVersionKind::Repair,
                        'title' => $title,
                        'summary' => $summary,
                        'body' => $body,
                        'faq' => $faq,
                        'meta_title' => $metaTitle,
                        'meta_description' => $metaDescription,
                        'section_version_ids' => $source->section_version_ids,
                        'input_hash' => $inputHash,
                        'created_by_admin_id' => $admin->id,
                        'synchronized_at' => now(),
                    ]);
                }

                if ($version->article_id) {
                    Article::query()->whereKey($version->article_id)->update([
                        'title' => $version->title,
                        'excerpt' => $version->summary,
                        'content' => $version->body,
                        'meta_description' => $version->meta_description,
                        'status' => 'draft',
                        'review_status' => 'pending',
                        'published_at' => null,
                    ]);
                }
                $lockedAttempt->forceFill([
                    'repaired_article_version_id' => $version->id,
                    'status' => 'succeeded',
                    'result' => ['changes' => $changes],
                    'error_message' => null,
                    'finished_at' => now(),
                ])->save();
                $this->markRepairStage($lockedProduction, $lockedAttempt, $version);
                $lockedProduction->forceFill([
                    'status' => ContentProductionStatus::Running,
                    'current_stage' => ContentProductionStage::QualityGate,
                    'last_error_message' => null,
                ])->save();
                ContentProductionEvent::query()->create([
                    'content_production_id' => $lockedProduction->id,
                    'content_stage_run_id' => $this->repairStageRun($lockedProduction)?->id,
                    'admin_id' => $admin->id,
                    'event' => 'quality.repaired',
                    'metadata' => [
                        'quality_report_id' => $report->id,
                        'repair_attempt_id' => $lockedAttempt->id,
                        'source_article_version_id' => $source->id,
                        'repaired_article_version_id' => $version->id,
                        'changes' => $changes,
                    ],
                ]);

                return $version;
            });
        } catch (Throwable $exception) {
            $attempt->forceFill([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'finished_at' => now(),
            ])->save();
            throw $exception;
        }

        $qualityReport = $this->qualityGateService->inspect($admin, $production->fresh(), $version);

        return [
            'attempt' => $attempt->fresh(),
            'article_version' => $version,
            'quality_report' => $qualityReport,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $issues
     * @return array{string, string, string, array<int, array<string, string>>, string, string, list<array<string, mixed>>}
     */
    private function applyRepairs(ContentProduction $production, ArticleVersion $source, array $issues): array
    {
        $title = $source->title;
        $summary = $source->summary;
        $body = $source->body;
        $faq = $source->faq ?? [];
        $metaTitle = $source->meta_title;
        $metaDescription = $source->meta_description;
        $changes = [];

        foreach ($issues as $issue) {
            if (($issue['code'] ?? '') === 'sensitive_word') {
                $word = trim((string) data_get($issue, 'metadata.word'));
                $replacement = trim((string) data_get($issue, 'metadata.replacement'));
                $field = (string) ($issue['field'] ?? '');
                if ($word === '' || $replacement === '') {
                    continue;
                }
                $before = compact('title', 'summary', 'body', 'metaDescription');
                match ($field) {
                    'title' => $title = str_replace($word, $replacement, $title),
                    'excerpt' => $summary = str_replace($word, $replacement, $summary),
                    'content' => $body = str_replace($word, $replacement, $body),
                    'meta_description' => $metaDescription = str_replace($word, $replacement, $metaDescription),
                    default => null,
                };
                if ($before !== compact('title', 'summary', 'body', 'metaDescription')) {
                    $changes[] = ['code' => 'sensitive_word', 'field' => $field, 'from' => $word, 'to' => $replacement];
                }
            }

            if (($issue['code'] ?? '') === 'faq_insufficient') {
                while (count($faq) < 3) {
                    $number = count($faq) + 1;
                    $faq[] = [
                        'question' => $number === 1 ? "{$production->topic}是什么？" : "{$production->topic}需要注意什么（{$number}）？",
                        'answer' => $summary,
                    ];
                }
                $body = preg_replace('/\R{2,}## 常见问题\R.*\z/msu', '', $body) ?: $body;
                $faqBody = collect($faq)->map(
                    fn (array $item): string => '### '.$item['question']."\n\n".$item['answer']
                )->implode("\n\n");
                $body = rtrim($body)."\n\n## 常见问题\n\n".$faqBody;
                $changes[] = ['code' => 'faq_insufficient', 'field' => 'faq', 'count' => count($faq)];
            }
        }

        $metaTitle = mb_substr($title, 0, 60);
        if ($metaDescription === '') {
            $metaDescription = mb_substr($summary, 0, 160);
        }

        return [$title, $summary, $body, $faq, $metaTitle, $metaDescription, $changes];
    }

    /** @param list<string> $selectedIssueIds */
    private function validateRequest(
        ContentProduction $production,
        QualityReport $report,
        array $selectedIssueIds,
    ): void {
        if ($report->content_production_id !== $production->id) {
            throw ValidationException::withMessages(['issue_ids' => '质量报告不属于当前生产项目。']);
        }
        if ($selectedIssueIds === []) {
            throw ValidationException::withMessages(['issue_ids' => '请至少选择一个可自动修复的问题。']);
        }
        if ($production->qualityReports()->latest('version')->value('id') !== $report->id) {
            throw ValidationException::withMessages(['issue_ids' => '只能修复当前最新质量报告。']);
        }
    }

    private function markRepairStage(
        ContentProduction $production,
        QualityRepairAttempt $attempt,
        ArticleVersion $version,
    ): void {
        $previousRun = $this->repairStageRun($production);
        $production->stageRuns()->create([
            'stage' => ContentProductionStage::TargetedRepair,
            'status' => ContentStageStatus::Succeeded,
            'sequence' => $previousRun?->sequence ?? 10,
            'attempt' => ((int) $production->stageRuns()
                ->where('stage', ContentProductionStage::TargetedRepair->value)
                ->max('attempt')) + 1,
            'contract_version' => 1,
            'output_payload' => [
                'repair_attempt_id' => $attempt->id,
                'article_version_id' => $version->id,
                'changes' => data_get($attempt->result, 'changes', []),
            ],
            'rule_version' => 'targeted-repair-v1',
            'started_at' => now(),
            'finished_at' => now(),
        ]);
    }

    private function repairStageRun(ContentProduction $production): ?ContentStageRun
    {
        return $production->stageRuns()
            ->where('stage', ContentProductionStage::TargetedRepair->value)
            ->latest('attempt')
            ->first();
    }
}
