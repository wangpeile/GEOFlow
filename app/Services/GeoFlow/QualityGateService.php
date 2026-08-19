<?php

namespace App\Services\GeoFlow;

use App\Enums\ContentEvidenceUsage;
use App\Enums\ContentProductionStage;
use App\Enums\ContentProductionStatus;
use App\Enums\ContentStageStatus;
use App\Enums\QualityIssueSeverity;
use App\Enums\QualityReportStatus;
use App\Models\Admin;
use App\Models\ArticleVersion;
use App\Models\ContentProduction;
use App\Models\ContentProductionEvent;
use App\Models\ContentStageRun;
use App\Models\QualityReport;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class QualityGateService
{
    public const RULESET_VERSION = 'quality-v2';

    public function __construct(
        private readonly ArticleRiskScanner $riskScanner,
        private readonly ChineseContentGuard $chineseContentGuard,
    ) {}

    public function inspect(Admin $admin, ContentProduction $production, ?ArticleVersion $articleVersion = null): QualityReport
    {
        $articleVersion ??= $production->articleVersions()->first();
        if (! $articleVersion) {
            throw ValidationException::withMessages(['quality' => '请先组装文章，再执行质量检查。']);
        }
        if ($articleVersion->content_production_id !== $production->id) {
            throw ValidationException::withMessages(['quality' => '文章版本不属于当前生产项目。']);
        }

        $risk = $this->riskScanner->scan($this->riskPayload($production, $articleVersion));
        $inputHash = hash('sha256', json_encode([
            'article_version_id' => $articleVersion->id,
            'article_input_hash' => $articleVersion->input_hash,
            'ruleset_version' => self::RULESET_VERSION,
            'risk_algorithm_version' => ArticleRiskScanner::SCAN_ALGORITHM_VERSION,
            'risk_dictionary_hash' => $risk['dictionary_hash'],
            'risk_content_hash' => $risk['content_hash'],
            'evidence_fingerprint' => $this->evidenceFingerprint($production),
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $existing = QualityReport::query()
            ->where('content_production_id', $production->id)
            ->where('input_hash', $inputHash)
            ->first();
        if ($existing) {
            return $existing;
        }

        $issues = $this->issues($production, $articleVersion, $risk);
        $blockerCount = collect($issues)->where('severity', QualityIssueSeverity::Blocker->value)->count();
        $warningCount = collect($issues)->where('severity', QualityIssueSeverity::Warning->value)->count();
        $status = $blockerCount > 0
            ? QualityReportStatus::Blocked
            : ($warningCount > 0 ? QualityReportStatus::Warning : QualityReportStatus::Passed);

        return DB::transaction(function () use (
            $admin,
            $production,
            $articleVersion,
            $inputHash,
            $issues,
            $blockerCount,
            $warningCount,
            $status,
            $risk,
        ): QualityReport {
            $lockedProduction = ContentProduction::query()->lockForUpdate()->findOrFail($production->id);
            $existing = QualityReport::query()
                ->where('content_production_id', $production->id)
                ->where('input_hash', $inputHash)
                ->first();
            if ($existing) {
                return $existing;
            }

            $report = QualityReport::query()->create([
                'content_production_id' => $lockedProduction->id,
                'article_version_id' => $articleVersion->id,
                'version' => ((int) QualityReport::query()
                    ->where('content_production_id', $lockedProduction->id)
                    ->max('version')) + 1,
                'status' => $status,
                'issues' => $issues,
                'summary' => [
                    'total' => count($issues),
                    'blockers' => $blockerCount,
                    'warnings' => $warningCount,
                    'repairable' => collect($issues)->where('repairable', true)->count(),
                ],
                'input_hash' => $inputHash,
                'ruleset_version' => self::RULESET_VERSION,
                'risk_snapshot' => [
                    'status' => $risk['status'],
                    'match_count' => $risk['match_count'],
                    'content_hash' => $risk['content_hash'],
                    'dictionary_hash' => $risk['dictionary_hash'],
                    'algorithm_version' => ArticleRiskScanner::SCAN_ALGORITHM_VERSION,
                ],
                'created_by_admin_id' => $admin->id,
                'checked_at' => now(),
            ]);

            $this->markStage($lockedProduction, $report);
            $lockedProduction->forceFill([
                'status' => $status !== QualityReportStatus::Blocked
                    ? ContentProductionStatus::WaitingReview
                    : ContentProductionStatus::WaitingInput,
                'current_stage' => $status !== QualityReportStatus::Blocked
                    ? ContentProductionStage::Review
                    : ContentProductionStage::TargetedRepair,
                'last_error_message' => $status === QualityReportStatus::Blocked
                    ? "质量检查存在 {$blockerCount} 个阻断项。"
                    : null,
            ])->save();
            ContentProductionEvent::query()->create([
                'content_production_id' => $lockedProduction->id,
                'content_stage_run_id' => $this->qualityStageRun($lockedProduction)?->id,
                'admin_id' => $admin->id,
                'event' => 'quality.checked',
                'metadata' => [
                    'quality_report_id' => $report->id,
                    'article_version_id' => $articleVersion->id,
                    'status' => $status->value,
                    'blockers' => $blockerCount,
                    'warnings' => $warningCount,
                ],
            ]);

            return $report;
        });
    }

    /**
     * @return list<array{id:string, code:string, severity:string, field:string, location:string, message:string, suggestion:?string, repairable:bool, metadata:array<string, mixed>}>
     */
    private function issues(ContentProduction $production, ArticleVersion $version, array $risk): array
    {
        $issues = [];
        foreach ([
            'title' => $version->title,
            'body' => $version->body,
            'summary' => $version->summary,
            'meta_description' => $version->meta_description,
        ] as $field => $value) {
            if (trim((string) $value) === '') {
                $issues[] = $this->issue(
                    'required_field_missing',
                    QualityIssueSeverity::Blocker,
                    $field,
                    $field,
                    "发布字段 {$field} 不能为空。",
                    null,
                    false,
                );

                continue;
            }

            try {
                $this->chineseContentGuard->validateField($field, (string) $value);
            } catch (ValidationException $exception) {
                $issues[] = $this->issue(
                    'chinese_language_invalid',
                    QualityIssueSeverity::Blocker,
                    $field,
                    $field,
                    collect($exception->errors())->flatten()->first() ?: '内容未通过简体中文检查。',
                    '请人工重写对应内容，确保以简体中文为主。',
                    false,
                );
            }
        }

        foreach ($this->emptySections($version->body) as $heading) {
            $issues[] = $this->issue(
                'empty_section',
                QualityIssueSeverity::Blocker,
                'body',
                $heading,
                "章节“{$heading}”没有有效正文。",
                '请补充该章节内容。',
                false,
            );
        }

        foreach ($risk['matches'] as $match) {
            $severity = $match['severity'] === 'blocked'
                ? QualityIssueSeverity::Blocker
                : QualityIssueSeverity::Warning;
            $issues[] = $this->issue(
                'sensitive_word',
                $severity,
                $match['field'],
                $match['snippet'],
                "检测到敏感词“{$match['word']}”（{$match['category']}）。",
                $match['suggestion'],
                is_string($match['suggestion']) && trim($match['suggestion']) !== '',
                ['word' => $match['word'], 'replacement' => $match['suggestion']],
            );
        }

        $issues = array_merge(
            $issues,
            $this->citationIssues($production, $version),
            $this->officialWebsiteIssues($production, $version),
        );
        $plainLength = mb_strlen(preg_replace('/\s+/u', '', strip_tags($version->body)) ?: '');
        $minimum = max(100, (int) data_get($production->context, 'length_min', 500));
        $maximum = max($minimum, (int) data_get($production->context, 'length_max', 5000));
        if ($plainLength < $minimum || $plainLength > $maximum) {
            $issues[] = $this->issue(
                'length_out_of_range',
                QualityIssueSeverity::Warning,
                'body',
                '全文',
                "正文长度为 {$plainLength} 字，不符合 {$minimum} 至 {$maximum} 字的目标范围。",
                '请按目标篇幅补充或精简内容。',
                false,
                ['actual' => $plainLength, 'minimum' => $minimum, 'maximum' => $maximum],
            );
        }

        if (count($version->faq ?? []) < 3) {
            $issues[] = $this->issue(
                'faq_insufficient',
                QualityIssueSeverity::Warning,
                'faq',
                '常见问题',
                '常见问题少于 3 条。',
                '可根据文章标题和摘要补足常见问题。',
                true,
            );
        }
        if (! str_contains($version->title.$version->body, $production->topic)) {
            $issues[] = $this->issue(
                'topic_not_covered',
                QualityIssueSeverity::Warning,
                'body',
                '全文',
                '标题和正文未完整出现生产主题。',
                '请自然补充主题，不要机械堆砌关键词。',
                false,
            );
        }
        foreach ($this->duplicateParagraphs($version->body) as $paragraph) {
            $issues[] = $this->issue(
                'duplicate_paragraph',
                QualityIssueSeverity::Warning,
                'body',
                mb_substr($paragraph, 0, 80),
                '正文包含重复段落。',
                '请删除或改写重复段落。',
                false,
            );
        }
        foreach ($this->longParagraphs($version->body) as $paragraph) {
            $issues[] = $this->issue(
                'paragraph_too_long',
                QualityIssueSeverity::Warning,
                'body',
                mb_substr($paragraph, 0, 80),
                '段落超过 500 字，可能影响阅读体验。',
                '请按语义拆分段落。',
                false,
            );
        }

        return array_values($issues);
    }

    /**
     * @return list<array{id:string, code:string, severity:string, field:string, location:string, message:string, suggestion:?string, repairable:bool, metadata:array<string, mixed>}>
     */
    private function citationIssues(ContentProduction $production, ArticleVersion $version): array
    {
        $issues = [];
        $evidences = $production->evidences()
            ->where('usage', '!=', ContentEvidenceUsage::Disabled->value)
            ->get(['id', 'usage', 'source_title', 'source_url']);
        foreach ($evidences->where('usage', ContentEvidenceUsage::MustCite) as $evidence) {
            $needles = array_filter([
                trim((string) $evidence->source_url),
                trim((string) $evidence->source_title),
            ]);
            if ($needles === [] || collect($needles)->contains(
                fn (string $needle): bool => str_contains($version->body, $needle)
            )) {
                continue;
            }
            $issues[] = $this->issue(
                'required_citation_missing',
                QualityIssueSeverity::Blocker,
                'body',
                (string) ($evidence->source_title ?: $evidence->source_url ?: "证据 {$evidence->id}"),
                '必须引用的证据未在正文中体现。',
                '请人工核对事实后添加来源引用。',
                false,
                ['evidence_id' => $evidence->id],
            );
        }

        preg_match_all('~https?://[^\s<>)"\']+~iu', $version->body, $matches);
        $internalUrls = collect(data_get($production->writing_rule_snapshot, 'settings.internal_links', []))
            ->pluck('url')
            ->filter();
        $allowedUrls = $evidences->pluck('source_url')->filter()->merge($internalUrls)->map(
            fn (string $url): string => rtrim($url, '/')
        )->unique()->values();
        foreach (array_unique($matches[0] ?? []) as $url) {
            if ($allowedUrls->contains(rtrim($url, '/'))) {
                continue;
            }
            $issues[] = $this->issue(
                'unverified_citation',
                QualityIssueSeverity::Blocker,
                'body',
                $url,
                '正文包含未登记在证据中心的外部链接，无法确认引用来源。',
                '请先将来源加入证据中心并核验内容，或删除该链接。',
                false,
                ['url' => $url],
            );
        }

        return $issues;
    }

    /**
     * @return list<array{id:string, code:string, severity:string, field:string, location:string, message:string, suggestion:?string, repairable:bool, metadata:array<string, mixed>}>
     */
    private function officialWebsiteIssues(ContentProduction $production, ArticleVersion $version): array
    {
        $settings = (array) data_get($production->writing_rule_snapshot, 'settings', []);
        if (($settings['publisher_identity'] ?? 'official_brand') !== 'official_brand') {
            return [];
        }

        $issues = [];
        $outsiderExpressions = collect(['该厂商', '该公司', '据了解', '据该公司介绍'])
            ->filter(fn (string $expression): bool => str_contains($version->body, $expression))
            ->values();
        if ($outsiderExpressions->isNotEmpty()) {
            $issues[] = $this->issue(
                'official_voice_inconsistent',
                QualityIssueSeverity::Warning,
                'body',
                '全文',
                '官网文章出现第三方观察者表达：'.$outsiderExpressions->implode('、').'。',
                '请改用“我们”或品牌名称，从厂商官方网站的第一方视角表达。',
                false,
                ['expressions' => $outsiderExpressions->all()],
            );
        }

        $configuredLinks = collect(($settings['include_internal_links'] ?? false) ? ($settings['internal_links'] ?? []) : [])
            ->pluck('url')
            ->filter()
            ->map(fn (string $url): string => rtrim($url, '/'))
            ->unique()
            ->values();
        if ($configuredLinks->isNotEmpty() && ! $configuredLinks->contains(
            fn (string $url): bool => str_contains($version->body, $url)
        )) {
            $issues[] = $this->issue(
                'official_internal_link_missing',
                QualityIssueSeverity::Warning,
                'body',
                '全文',
                '写作规则已启用官网内链，但正文没有使用任何已配置的内部链接。',
                '请从写作规则的内链白名单中选择与正文语义相关的页面加入文章。',
                false,
                ['configured_count' => $configuredLinks->count()],
            );
        }

        return $issues;
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{id:string, code:string, severity:string, field:string, location:string, message:string, suggestion:?string, repairable:bool, metadata:array<string, mixed>}
     */
    private function issue(
        string $code,
        QualityIssueSeverity $severity,
        string $field,
        string $location,
        string $message,
        ?string $suggestion,
        bool $repairable,
        array $metadata = [],
    ): array {
        return [
            'id' => substr(hash('sha256', $code.'|'.$field.'|'.$location.'|'.$message), 0, 20),
            'code' => $code,
            'severity' => $severity->value,
            'field' => $field,
            'location' => $location,
            'message' => $message,
            'suggestion' => $suggestion,
            'repairable' => $repairable,
            'metadata' => $metadata,
        ];
    }

    /** @return list<string> */
    private function emptySections(string $body): array
    {
        preg_match_all('/^#{2,3}\s+(.+)\R(.*?)(?=^#{2,3}\s+|\z)/msu', $body, $matches, PREG_SET_ORDER);

        return collect($matches)->filter(function (array $match): bool {
            $content = preg_replace('/\s+/u', '', strip_tags(trim((string) ($match[2] ?? '')))) ?: '';

            return mb_strlen($content) < 6;
        })->map(fn (array $match): string => trim((string) $match[1]))->values()->all();
    }

    /** @return list<string> */
    private function duplicateParagraphs(string $body): array
    {
        $paragraphs = collect(preg_split('/\R{2,}/u', $body) ?: [])
            ->map(fn (string $paragraph): string => trim($paragraph))
            ->filter(fn (string $paragraph): bool => ! str_starts_with($paragraph, '#') && mb_strlen($paragraph) >= 40);

        return $paragraphs->groupBy(
            fn (string $paragraph): string => preg_replace('/\s+/u', '', $paragraph) ?: $paragraph
        )->filter(fn ($group): bool => $group->count() > 1)
            ->map(fn ($group): string => $group->first())
            ->values()
            ->all();
    }

    /** @return list<string> */
    private function longParagraphs(string $body): array
    {
        return collect(preg_split('/\R{2,}/u', $body) ?: [])
            ->map(fn (string $paragraph): string => trim($paragraph))
            ->filter(fn (string $paragraph): bool => ! str_starts_with($paragraph, '#') && mb_strlen($paragraph) > 500)
            ->values()
            ->all();
    }

    private function evidenceFingerprint(ContentProduction $production): string
    {
        return hash('sha256', $production->evidences()
            ->orderBy('id')
            ->get(['id', 'usage', 'source_title', 'source_url', 'updated_at'])
            ->toJson(JSON_UNESCAPED_UNICODE));
    }

    /** @return array<string, string> */
    private function riskPayload(ContentProduction $production, ArticleVersion $version): array
    {
        return [
            'title' => $version->title,
            'excerpt' => $version->summary,
            'content' => $version->body,
            'keywords' => $production->topic,
            'meta_description' => $version->meta_description,
        ];
    }

    private function markStage(ContentProduction $production, QualityReport $report): void
    {
        $previousRun = $this->qualityStageRun($production);
        $production->stageRuns()->create([
            'stage' => ContentProductionStage::QualityGate,
            'status' => ContentStageStatus::Succeeded,
            'sequence' => $previousRun?->sequence ?? 9,
            'attempt' => ((int) $production->stageRuns()
                ->where('stage', ContentProductionStage::QualityGate->value)
                ->max('attempt')) + 1,
            'contract_version' => 1,
            'input_hash' => $report->input_hash,
            'output_payload' => [
                'quality_report_id' => $report->id,
                'status' => $report->status->value,
                'summary' => $report->summary,
            ],
            'rule_version' => self::RULESET_VERSION,
            'started_at' => now(),
            'finished_at' => now(),
        ]);
    }

    private function qualityStageRun(ContentProduction $production): ?ContentStageRun
    {
        return $production->stageRuns()
            ->where('stage', ContentProductionStage::QualityGate->value)
            ->latest('attempt')
            ->first();
    }
}
