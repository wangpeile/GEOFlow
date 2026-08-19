<?php

namespace App\Services\GeoFlow;

use App\Ai\Agents\MarkdownContentWriterAgent;
use App\Contracts\GeoFlow\ContentSectionGenerator;
use App\Models\AiModel;
use App\Models\ContentProduction;
use App\Models\ContentSectionVersion;
use App\Support\GeoFlow\ApiKeyCrypto;
use App\Support\GeoFlow\OpenAiRuntimeProvider;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class LaravelAiContentSectionGenerator implements ContentSectionGenerator
{
    public function __construct(private readonly ApiKeyCrypto $apiKeyCrypto) {}

    public function generate(ContentProduction $production, ContentSectionVersion $section): array
    {
        $model = $this->resolveModel($production);
        $providerUrl = OpenAiRuntimeProvider::resolveChatBaseUrl((string) $model->api_url);
        $apiKey = $this->apiKeyCrypto->decrypt((string) $model->getRawOriginal('api_key'));
        if ($providerUrl === '' || $apiKey === '') {
            throw new RuntimeException('写作模型的 API 地址或密钥未配置。');
        }

        $driver = OpenAiRuntimeProvider::resolveChatDriver($providerUrl, (string) $model->model_id);
        $provider = OpenAiRuntimeProvider::registerProvider('content_section', $driver, $providerUrl, $apiKey);
        $agent = new MarkdownContentWriterAgent(
            instructions: $this->instructions($production),
            maxTokens: max(512, (int) ($model->max_tokens ?: config('geoflow.content_max_tokens', 8192))),
        );

        try {
            $response = $agent->prompt(
                $this->prompt($production, $section),
                [],
                $provider,
                (string) $model->model_id,
            );
        } catch (Throwable $exception) {
            throw new RuntimeException(
                '章节生成失败：'.OpenAiRuntimeProvider::normalizeApiException($exception, $providerUrl),
                0,
                $exception,
            );
        }

        $content = OpenAiRuntimeProvider::normalizeGeneratedText((string) ($response->text ?? ''));
        if ($content === '') {
            throw new RuntimeException('章节生成失败：模型返回空内容。');
        }

        AiModel::query()->whereKey($model->id)->update([
            'used_today' => DB::raw('COALESCE(used_today, 0) + 1'),
            'total_used' => DB::raw('COALESCE(total_used, 0) + 1'),
            'updated_at' => now(),
        ]);

        return [
            'content' => $content,
            'model' => (string) $model->model_id,
            'source' => 'laravel_ai_sdk',
        ];
    }

    private function resolveModel(ContentProduction $production): AiModel
    {
        $taskModel = $production->task?->aiModel;
        if ($taskModel instanceof AiModel && $taskModel->status === 'active' && $taskModel->model_type !== 'embedding') {
            return $taskModel;
        }

        $model = AiModel::query()
            ->where('status', 'active')
            ->where(function ($query): void {
                $query->whereNull('model_type')->orWhere('model_type', '!=', 'embedding');
            })
            ->orderBy('failover_priority')
            ->orderBy('id')
            ->first();

        if (! $model) {
            throw new RuntimeException('没有可用的正文写作模型，请先启用一个非 Embedding 模型。');
        }

        return $model;
    }

    private function instructions(ContentProduction $production): string
    {
        $settings = (array) data_get($production->writing_rule_snapshot, 'settings', []);
        $identity = (string) ($settings['publisher_identity'] ?? 'official_brand');
        $brandName = trim((string) ($settings['brand_name'] ?? ''));
        $brandProfile = trim((string) ($settings['brand_profile'] ?? ''));
        $custom = trim((string) ($settings['instructions'] ?? ''));
        $voice = $identity === 'independent_editorial'
            ? '以独立第三方编辑视角客观陈述，不使用“我们”冒充厂商。'
            : '这是产品厂商官方网站的首发文章。以厂商/品牌自身视角写作，使用“我们”表达自身能力、经验和建议；不要把品牌写成被分析的第三方，不要使用“该厂商”“该公司”“据了解”等旁观者口吻。';

        return <<<'PROMPT'
你是 GEOFlow 的中文内容章节写作 Agent。只输出当前章节正文，不重复章节标题，不输出写作说明、英文提示词、JSON 或代码围栏。
全文使用简体中文；品牌名、产品名、标准名和必要缩写可以保留英文。
首句直接回答本节问题。每个自然段只表达一个核心观点，明确写出主题实体，避免空泛开场。
只使用提供的证据；没有证据支持的数字、案例和结论不得编造。需要引用时使用“来源标题”作为自然语言出处。
PROMPT."\n{$voice}"
            .($brandName !== '' ? "\n品牌/厂商名称：{$brandName}。" : '')
            .($brandProfile !== '' ? "\n品牌资料：{$brandProfile}" : '')
            .($custom !== '' ? "\n补充写作要求：{$custom}" : '');
    }

    private function prompt(ContentProduction $production, ContentSectionVersion $section): string
    {
        $evidences = $production->evidences()
            ->whereIn('id', $section->evidence_ids ?? [])
            ->where('usage', '!=', 'disabled')
            ->get(['id', 'source_title', 'source_url', 'content_snapshot'])
            ->map(fn ($evidence): string => sprintf(
                "[证据 #%d] %s%s\n%s",
                $evidence->id,
                $evidence->source_title ?: '未命名来源',
                $evidence->source_url ? '（'.$evidence->source_url.'）' : '',
                mb_substr((string) $evidence->content_snapshot, 0, 3000),
            ))
            ->implode("\n\n");

        $settings = (array) data_get($production->writing_rule_snapshot, 'settings', []);
        $links = collect(($settings['include_internal_links'] ?? false) ? ($settings['internal_links'] ?? []) : [])
            ->filter(fn (mixed $link): bool => is_array($link) && filled($link['anchor'] ?? null) && filled($link['url'] ?? null))
            ->take(20)
            ->map(fn (array $link): string => '- ['.trim((string) $link['anchor']).']('.trim((string) $link['url']).')')
            ->implode("\n");
        $linkGuidance = $links !== ''
            ? "\n\n可用官网内链：\n{$links}\n仅在与本节语义直接相关时自然加入 0 至 1 条 Markdown 内链；必须使用清单中的锚文本和 URL，不得编造或修改链接。"
            : "\n\n本节没有可用的官网内链，不得编造链接。";

        return "文章主题：{$production->topic}\n"
            ."当前章节：{$section->heading}\n"
            .'章节层级：'.strtoupper($section->level)."\n"
            ."目标：写成可独立理解、可被检索引用的中文内容块，约 300 至 700 个汉字。\n\n"
            ."可用证据：\n".($evidences !== '' ? $evidences : '没有可用证据；不得编造事实或引用。')
            .$linkGuidance;
    }
}
