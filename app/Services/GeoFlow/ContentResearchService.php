<?php

namespace App\Services\GeoFlow;

use App\Enums\ContentEvidenceSourceType;
use App\Enums\ContentEvidenceUsage;
use App\Models\Admin;
use App\Models\ContentProduction;
use App\Models\ContentResearchReport;
use App\Models\UrlImportJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ContentResearchService
{
    public function __construct(private readonly ContentWebResearchGenerator $webResearchGenerator) {}

    public function create(Admin $admin, ContentProduction $production, array $input): ContentResearchReport
    {
        $jobs = UrlImportJob::query()
            ->select(['id', 'url', 'normalized_url', 'source_domain', 'page_title', 'status', 'result_json', 'finished_at'])
            ->whereIn('id', $input['url_import_job_ids'] ?? [])
            ->where('status', 'completed')
            ->get();
        $sources = $jobs->map(fn (UrlImportJob $job): array => $this->source($job))
            ->filter(fn (array $source): bool => $source['content'] !== '')
            ->values();

        $webResearch = null;
        $webResearchError = null;
        if ((bool) ($input['use_web_search'] ?? false)) {
            try {
                $webResearch = $this->webResearchGenerator->generate($production, (string) $input['keyword']);
            } catch (\Throwable $exception) {
                $webResearchError = $exception->getMessage();
            }
        }

        $analysis = $sources->isEmpty()
            ? $this->fallbackAnalysis()
            : $this->analyze((string) $input['keyword'], $sources->all());
        if ($webResearch !== null) {
            $analysis = array_replace($analysis, $webResearch['analysis']);
            $analysis['competitors'] = array_merge(
                (array) ($analysis['competitors'] ?? []),
                $webResearch['sources'],
            );
        }
        if ($webResearchError !== null) {
            $analysis['web_research_error'] = $webResearchError;
        }
        $reportSources = collect($sources)
            ->map(fn (array $source): array => collect($source)->except('content')->all())
            ->merge($webResearch['sources'] ?? [])
            ->unique('url')
            ->values();
        $hasUsableResearch = $sources->isNotEmpty() || $webResearch !== null;
        $sourceMode = match (true) {
            $sources->isNotEmpty() && $webResearch !== null => 'url_import_and_web_search',
            $webResearch !== null => 'ai_web_search',
            $sources->isNotEmpty() => 'url_import',
            default => 'knowledge_url_fallback',
        };

        return DB::transaction(function () use ($admin, $production, $input, $sources, $analysis, $reportSources, $hasUsableResearch, $sourceMode, $webResearch, $webResearchError): ContentResearchReport {
            ContentProduction::query()->whereKey($production->getKey())->lockForUpdate()->firstOrFail();
            $report = $production->researchReports()->create([
                'created_by_admin_id' => $admin->getKey(),
                'keyword' => trim((string) $input['keyword']),
                'status' => $hasUsableResearch ? 'completed' : 'fallback',
                'source_mode' => $sourceMode,
                'sources' => $reportSources->all(),
                'analysis' => $analysis,
                'error_message' => $webResearchError,
                'collected_at' => now(),
            ]);

            foreach ($sources as $source) {
                $production->evidences()->updateOrCreate([
                    'source_key' => 'serp_research:'.$report->getKey().':'.$source['job_id'],
                ], [
                    'url_import_job_id' => $source['job_id'],
                    'created_by_admin_id' => $admin->getKey(),
                    'source_type' => ContentEvidenceSourceType::SerpResearch,
                    'usage' => ContentEvidenceUsage::ReferenceOnly,
                    'source_url' => $source['url'],
                    'source_title' => $source['title'],
                    'content_snapshot' => $source['content'],
                    'excerpt' => $source['excerpt'],
                    'metadata' => ['research_report_id' => $report->getKey(), 'keyword' => $input['keyword']],
                    'collected_at' => $source['collected_at'],
                ]);
            }

            $production->events()->create([
                'admin_id' => $admin->getKey(),
                'event' => 'content_research_created',
                'metadata' => [
                    'report_id' => $report->getKey(),
                    'source_count' => $reportSources->count(),
                    'source_mode' => $sourceMode,
                    'web_research_requested' => (bool) ($input['use_web_search'] ?? false),
                    'web_research_completed' => $webResearch !== null,
                ],
            ]);

            return $report;
        });
    }

    /** @return array<string,mixed> */
    private function source(UrlImportJob $job): array
    {
        $result = json_decode((string) $job->result_json, true);
        $result = is_array($result) ? $result : [];
        $page = is_array($result['page'] ?? null) ? $result['page'] : [];
        $cleaned = is_array($result['cleaned'] ?? null) ? $result['cleaned'] : [];
        $content = trim((string) ($page['text'] ?? $cleaned['text'] ?? ''));

        return [
            'job_id' => (int) $job->getKey(),
            'url' => (string) ($job->normalized_url ?: $job->url),
            'title' => trim((string) ($page['title'] ?? $job->page_title)) ?: (string) $job->source_domain,
            'excerpt' => Str::limit(trim((string) ($page['summary'] ?? $result['summary'] ?? $content)), 500, ''),
            'content' => mb_substr($content, 0, 30000),
            'collected_at' => ($job->finished_at ?? now())->toISOString(),
        ];
    }

    /** @param array<int,array<string,mixed>> $sources */
    private function analyze(string $keyword, array $sources): array
    {
        $all = implode("\n", array_map(fn (array $source): string => $source['title'].' '.$source['excerpt'], $sources));
        $terms = collect(preg_split('/[^\p{Han}A-Za-z0-9]+/u', $all, -1, PREG_SPLIT_NO_EMPTY) ?: [])
            ->filter(fn (string $term): bool => mb_strlen($term) >= 2 && mb_strlen($term) <= 20)
            ->countBy()->sortDesc()->keys()->take(12)->values()->all();

        return [
            'summary' => "已基于 {$sources[0]['title']} 等 ".count($sources)." 个已采集来源，整理“{$keyword}”的竞品覆盖情况。",
            'competitors' => array_map(fn (array $source): array => [
                'title' => $source['title'], 'url' => $source['url'], 'excerpt' => $source['excerpt'],
            ], $sources),
            'covered_terms' => $terms,
            'content_gaps' => [
                '补充自有产品、服务或案例的可验证信息',
                '补充来源、采集时间与可核验引用',
                '用常见问题覆盖读者决策阶段的具体疑问',
            ],
            'recommendations' => ['先确认搜索意图', '使用自有知识库补齐差异化事实', '生成后执行质量检查与人工审核'],
        ];
    }

    private function fallbackAnalysis(): array
    {
        return [
            'summary' => '没有可用的已采集搜索来源，本次未执行竞品内容分析。',
            'competitors' => [], 'covered_terms' => [],
            'content_gaps' => ['请先完成 URL 智能采集，或直接使用知识库与 URL 证据继续创作。'],
            'recommendations' => ['SERP 不可用不会阻断内容生产主流程。'],
        ];
    }
}
