<?php

namespace App\Services\GeoFlow;

use App\Ai\Agents\MarkdownContentWriterAgent;
use App\Models\AiModel;
use App\Models\ContentDirectionVersion;
use App\Models\ContentProduction;
use App\Support\GeoFlow\ApiKeyCrypto;
use App\Support\GeoFlow\OpenAiRuntimeProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Generates the editorial choices shown in the content-direction workbench.
 *
 * This is deliberately separate from body generation: users may review, edit,
 * and confirm every generated choice before section writing starts.
 */
final class ContentDirectionAiGenerator
{
    public function __construct(private readonly ApiKeyCrypto $apiKeyCrypto) {}

    /** @return array{candidates: list<array{id:string,title:string,rationale:string}>, model:string} */
    public function titles(ContentProduction $production, ContentDirectionVersion $brief): array
    {
        $data = $this->ask(
            $production,
            $brief,
            '请生成恰好 5 个不同的简体中文文章标题。每个标题不超过 60 个中文字符，并说明其搜索意图或内容角度。',
            <<<'JSON'
{"candidates":[{"title":"标题","rationale":"选择理由"}]}
JSON,
        );

        $candidates = collect((array) ($data['payload']['candidates'] ?? []))
            ->map(function (mixed $item): ?array {
                if (! is_array($item)) {
                    return null;
                }

                $title = trim((string) ($item['title'] ?? ''));
                $rationale = trim((string) ($item['rationale'] ?? ''));
                if ($title === '' || Str::length($title) > 60 || ! preg_match('/\p{Han}/u', $title)) {
                    return null;
                }

                return [
                    'id' => (string) Str::uuid(),
                    'title' => $title,
                    'rationale' => Str::limit(
                        $rationale !== '' && preg_match('/\\p{Han}/u', $rationale)
                            ? $rationale
                            : '与内容简报保持一致',
                        80,
                        '',
                    ),
                ];
            })
            ->filter()
            ->unique('title')
            ->take(5)
            ->values()
            ->all();

        if (count($candidates) !== 5) {
            throw new RuntimeException('模型未返回恰好 5 个可用标题候选，无法提供可靠的选择。');
        }

        return ['candidates' => $candidates, 'model' => $data['model']];
    }

    /** @return array{candidates: list<array{id:string,name:string,nodes:list<array{id:string,level:string,heading:string,evidence_ids:list<int>}>>>, model:string} */
    public function outlines(
        ContentProduction $production,
        ContentDirectionVersion $brief,
        string $selectedTitle,
    ): array {
        $data = $this->ask(
            $production,
            $brief,
            "已确认标题：{$selectedTitle}\n请生成两个不同结构的大纲。每个大纲应从 H2 开始，可在 H2 后使用 H3；至少 5 个标题，覆盖读者问题、核心判断、可执行建议与风险提示。",
            <<<'JSON'
{"candidates":[{"name":"大纲名称","nodes":[{"level":"h2","heading":"章节标题"},{"level":"h3","heading":"子章节标题"}]}]}
JSON,
        );

        $evidenceIds = collect((array) ($brief->payload['evidence_ids'] ?? []))
            ->map(static fn (mixed $id): int => (int) $id)
            ->filter()
            ->values()
            ->all();
        $candidates = collect((array) ($data['payload']['candidates'] ?? []))
            ->map(function (mixed $item) use ($evidenceIds): ?array {
                if (! is_array($item)) {
                    return null;
                }

                $nodes = [];
                $seenH2 = false;
                foreach ((array) ($item['nodes'] ?? []) as $node) {
                    if (! is_array($node)) {
                        continue;
                    }
                    $level = strtolower(trim((string) ($node['level'] ?? '')));
                    $heading = trim((string) ($node['heading'] ?? ''));
                    if (
                        ! in_array($level, ['h2', 'h3'], true)
                        || $heading === ''
                        || Str::length($heading) > 80
                        || ! preg_match('/\\p{Han}/u', $heading)
                    ) {
                        continue;
                    }
                    if ($level === 'h3' && ! $seenH2) {
                        continue;
                    }
                    $seenH2 = $seenH2 || $level === 'h2';
                    $nodes[] = [
                        'id' => (string) Str::uuid(),
                        'level' => $level,
                        'heading' => $heading,
                        'evidence_ids' => $evidenceIds,
                    ];
                }

                if (count($nodes) < 5 || ($nodes[0]['level'] ?? null) !== 'h2') {
                    return null;
                }

                return [
                    'id' => (string) Str::uuid(),
                    'name' => Str::limit(
                        preg_match('/\\p{Han}/u', (string) ($item['name'] ?? ''))
                            ? trim((string) $item['name'])
                            : 'AI 建议大纲',
                        40,
                        '',
                    ),
                    'nodes' => $nodes,
                ];
            })
            ->filter()
            ->take(2)
            ->values()
            ->all();

        if (count($candidates) < 2) {
            throw new RuntimeException('模型返回的大纲候选不足，无法提供可靠的对比。');
        }

        return ['candidates' => $candidates, 'model' => $data['model']];
    }

    /** @return array{payload: array<string,mixed>, model:string} */
    private function ask(ContentProduction $production, ContentDirectionVersion $brief, string $task, string $shape): array
    {
        $model = $this->resolveModel();
        $providerUrl = OpenAiRuntimeProvider::resolveChatBaseUrl((string) $model->api_url);
        $apiKey = $this->apiKeyCrypto->decrypt((string) $model->getRawOriginal('api_key'));
        if ($providerUrl === '' || $apiKey === '') {
            throw new RuntimeException('写作模型的 API 地址或密钥未配置。');
        }

        $provider = OpenAiRuntimeProvider::registerProvider(
            'content_direction',
            OpenAiRuntimeProvider::resolveChatDriver($providerUrl, (string) $model->model_id),
            $providerUrl,
            $apiKey,
        );
        $agent = new MarkdownContentWriterAgent(
            instructions: '你是 GEOFlow 的中文内容策划 Agent。只能使用给定主题、内容简报和证据摘要，不得编造数据、案例、引用、排名或事实。输出必须是简体中文且仅输出合法 JSON。',
            maxTokens: min(4096, max(1024, (int) ($model->max_tokens ?: 2048))),
        );

        $briefPayload = [
            'article_type' => $brief->payload['article_type'] ?? '',
            'target_audience' => $brief->payload['target_audience'] ?? '',
            'search_intent' => $brief->payload['search_intent'] ?? '',
            'content_angle' => $brief->payload['content_angle'] ?? '',
            'must_cover' => $brief->payload['must_cover'] ?? [],
            'avoid_topics' => $brief->payload['avoid_topics'] ?? [],
        ];
        $evidence = $production->evidences()
            ->whereIn('id', (array) ($brief->payload['evidence_ids'] ?? []))
            ->orderBy('id')
            ->get(['id', 'source_title', 'excerpt'])
            ->map(static fn ($item): array => [
                'id' => $item->id,
                'title' => $item->source_title,
                'excerpt' => Str::limit((string) $item->excerpt, 500, ''),
            ])->all();
        $research = Schema::hasTable('content_research_reports')
            ? $production->researchReports()
                ->where('status', 'completed')
                ->latest('collected_at')
                ->limit(2)
                ->get(['keyword', 'source_mode', 'analysis', 'sources', 'collected_at'])
                ->map(static fn ($report): array => [
                    'keyword' => $report->keyword,
                    'mode' => $report->source_mode,
                    'summary' => Str::limit((string) data_get($report->analysis, 'summary'), 1000, ''),
                    'content_gaps' => array_slice((array) data_get($report->analysis, 'content_gaps', []), 0, 5),
                    'recommendations' => array_slice((array) data_get($report->analysis, 'recommendations', []), 0, 5),
                    'citations' => collect((array) $report->sources)
                        ->filter(static fn ($source): bool => is_array($source) && filled(data_get($source, 'url')))
                        ->map(static fn (array $source): array => ['title' => data_get($source, 'title'), 'url' => data_get($source, 'url')])
                        ->take(5)
                        ->values()
                        ->all(),
                ])->all()
            : [];

        try {
            $response = $agent->prompt(
                "主题：{$production->topic}\n内容简报：".json_encode($briefPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    ."\n可用证据摘要：".json_encode($evidence, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    ."\n外部研究摘要（仅用于选题和结构补充，不能将其写成未经证据验证的事实）：".json_encode($research, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    ."\n任务：{$task}\n输出 JSON 结构：{$shape}",
                [],
                $provider,
                (string) $model->model_id,
            );
            $raw = OpenAiRuntimeProvider::normalizeGeneratedText((string) ($response->text ?? ''));
            $payload = json_decode(preg_replace('/^```(?:json)?\s*|\s*```$/u', '', $raw) ?: $raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            throw new RuntimeException(
                '内容方向 AI 生成失败：'.OpenAiRuntimeProvider::normalizeApiException($exception, $providerUrl),
                0,
                $exception,
            );
        }

        if (! is_array($payload)) {
            throw new RuntimeException('内容方向 AI 未返回合法 JSON。');
        }

        AiModel::query()->whereKey($model->id)->update([
            'used_today' => DB::raw('COALESCE(used_today, 0) + 1'),
            'total_used' => DB::raw('COALESCE(total_used, 0) + 1'),
            'updated_at' => now(),
        ]);

        return ['payload' => $payload, 'model' => (string) $model->model_id];
    }

    private function resolveModel(): AiModel
    {
        $model = AiModel::query()
            ->where('status', 'active')
            ->where(fn ($query) => $query->whereNull('model_type')->orWhere('model_type', '!=', 'embedding'))
            ->orderBy('failover_priority')
            ->orderBy('id')
            ->first();

        if (! $model) {
            throw new RuntimeException('没有可用的写作模型。');
        }

        return $model;
    }
}
