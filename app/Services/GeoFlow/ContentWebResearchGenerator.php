<?php

namespace App\Services\GeoFlow;

use App\Ai\Agents\MarkdownContentWriterAgent;
use App\Models\AiModel;
use App\Models\ContentProduction;
use App\Support\GeoFlow\ApiKeyCrypto;
use App\Support\GeoFlow\OpenAiRuntimeProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Ai\Providers\Tools\WebSearch;
use Laravel\Ai\Responses\Data\UrlCitation;
use RuntimeException;
use Throwable;

/**
 * Uses a provider-supported web search tool to create a planning brief.
 *
 * The response only records citation indexes.  It never treats search output as
 * an article evidence snapshot; a cited URL must still go through URL import
 * before it can be selected as a factual source for the article.
 */
final class ContentWebResearchGenerator
{
    public function __construct(private readonly ApiKeyCrypto $apiKeyCrypto) {}

    /**
     * @return array{analysis: array<string,mixed>, sources: list<array{url:string,title:string}>}
     */
    public function generate(ContentProduction $production, string $keyword): array
    {
        $model = $this->resolveModel();
        $providerUrl = OpenAiRuntimeProvider::resolveChatBaseUrl((string) $model->api_url);
        $apiKey = $this->apiKeyCrypto->decrypt((string) $model->getRawOriginal('api_key'));
        if ($providerUrl === '' || $apiKey === '') {
            throw new RuntimeException('联网研究模型的 API 地址或密钥未配置。');
        }

        $driver = OpenAiRuntimeProvider::resolveChatDriver($providerUrl, (string) $model->model_id);
        if (! in_array($driver, ['openai', 'gemini', 'anthropic'], true)) {
            throw new RuntimeException('当前写作模型不支持联网检索。请在模型管理中启用 OpenAI、Gemini 或 Anthropic 的写作模型，或先使用 URL 智能采集。');
        }

        $provider = OpenAiRuntimeProvider::registerProvider('content_web_research', $driver, $providerUrl, $apiKey);
        $agent = new MarkdownContentWriterAgent(
            instructions: '你是 GEOFlow 的中文内容研究 Agent。必须先使用联网检索工具，再根据搜索结果输出简体中文。不得编造来源、数据、排名、案例或引用。只输出合法 JSON。',
            tools: [(new WebSearch)->max(5)],
            maxTokens: min(4096, max(1024, (int) ($model->max_tokens ?: 2048))),
        );

        try {
            $response = $agent->prompt(
                "请为文章主题“{$production->topic}”研究关键词“{$keyword}”。聚焦读者搜索意图、已覆盖角度、内容缺口与差异化建议。不要把搜索摘要当成已验证事实。\n"
                .'输出 JSON 结构：{"summary":"研究摘要","covered_terms":["主题词"],"content_gaps":["内容缺口"],"recommendations":["写作建议"]}',
                [],
                $provider,
                (string) $model->model_id,
            );
            $raw = OpenAiRuntimeProvider::normalizeGeneratedText((string) ($response->text ?? ''));
            $payload = json_decode(preg_replace('/^```(?:json)?\s*|\s*```$/u', '', $raw) ?: $raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            throw new RuntimeException(
                '联网研究失败：'.OpenAiRuntimeProvider::normalizeApiException($exception, $providerUrl),
                0,
                $exception,
            );
        }

        if (! is_array($payload)) {
            throw new RuntimeException('联网研究模型未返回合法 JSON。');
        }

        $sources = collect($response->meta->citations ?? [])
            ->filter(static fn (mixed $citation): bool => $citation instanceof UrlCitation && filter_var($citation->url, FILTER_VALIDATE_URL) !== false)
            ->map(static fn (UrlCitation $citation): array => [
                'url' => $citation->url,
                'title' => trim((string) $citation->title) ?: parse_url($citation->url, PHP_URL_HOST) ?: '未命名联网引用',
            ])
            ->unique('url')
            ->take(10)
            ->values()
            ->all();

        if ($sources === []) {
            throw new RuntimeException('联网研究未返回可追溯引用，已拒绝将其用于内容研究。');
        }

        AiModel::query()->whereKey($model->id)->update([
            'used_today' => DB::raw('COALESCE(used_today, 0) + 1'),
            'total_used' => DB::raw('COALESCE(total_used, 0) + 1'),
            'updated_at' => now(),
        ]);

        return [
            'analysis' => [
                'summary' => $this->chineseText($payload['summary'] ?? '', '已完成联网研究，请结合下方引用继续核验。'),
                'covered_terms' => $this->chineseList($payload['covered_terms'] ?? [], 12),
                'content_gaps' => $this->chineseList($payload['content_gaps'] ?? [], 8),
                'recommendations' => $this->chineseList($payload['recommendations'] ?? [], 8),
                'research_mode' => 'ai_web_search',
                'model' => (string) $model->model_id,
            ],
            'sources' => $sources,
        ];
    }

    private function resolveModel(): AiModel
    {
        $models = AiModel::query()
            ->where('status', 'active')
            ->where(fn ($query) => $query->whereNull('model_type')->orWhere('model_type', '!=', 'embedding'))
            ->orderBy('failover_priority')
            ->orderBy('id')
            ->get();

        foreach ($models as $model) {
            $url = OpenAiRuntimeProvider::resolveChatBaseUrl((string) $model->api_url);
            if (in_array(OpenAiRuntimeProvider::resolveChatDriver($url, (string) $model->model_id), ['openai', 'gemini', 'anthropic'], true)) {
                return $model;
            }
        }

        throw new RuntimeException('没有可用的联网研究模型。');
    }

    private function chineseText(mixed $value, string $fallback): string
    {
        $value = Str::limit(trim((string) $value), 1200, '');

        return $value !== '' && preg_match('/\p{Han}/u', $value) ? $value : $fallback;
    }

    /** @return list<string> */
    private function chineseList(mixed $items, int $limit): array
    {
        return collect((array) $items)
            ->map(fn (mixed $item): string => Str::limit(trim((string) $item), 180, ''))
            ->filter(fn (string $item): bool => $item !== '' && preg_match('/\p{Han}/u', $item))
            ->unique()
            ->take($limit)
            ->values()
            ->all();
    }
}
