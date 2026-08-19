<?php

namespace App\Services\GeoFlow;

use App\Ai\Agents\MarkdownContentWriterAgent;
use App\Contracts\GeoFlow\ContentVariantGenerator;
use App\Models\AiModel;
use App\Models\Article;
use App\Support\GeoFlow\ApiKeyCrypto;
use App\Support\GeoFlow\OpenAiRuntimeProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;
use Throwable;

final class LaravelAiContentVariantGenerator implements ContentVariantGenerator
{
    public function __construct(private readonly ApiKeyCrypto $apiKeyCrypto) {}

    public function generate(Article $article, string $platform, array $platformRules): array
    {
        $model = $this->resolveModel($article);
        $providerUrl = OpenAiRuntimeProvider::resolveChatBaseUrl((string) $model->api_url);
        $apiKey = $this->apiKeyCrypto->decrypt((string) $model->getRawOriginal('api_key'));
        if ($providerUrl === '' || $apiKey === '') {
            throw new RuntimeException('写作模型的 API 地址或密钥未配置。');
        }

        $driver = OpenAiRuntimeProvider::resolveChatDriver($providerUrl, (string) $model->model_id);
        $provider = OpenAiRuntimeProvider::registerProvider('content_variant', $driver, $providerUrl, $apiKey);
        $agent = new MarkdownContentWriterAgent(
            instructions: $this->instructions(),
            maxTokens: max(1024, (int) ($model->max_tokens ?: config('geoflow.content_max_tokens', 8192))),
        );

        try {
            $response = $agent->prompt(
                $this->prompt($article, $platform, $platformRules),
                [],
                $provider,
                (string) $model->model_id,
            );
            $result = $this->decode((string) ($response->text ?? ''), $platformRules);
        } catch (Throwable $exception) {
            throw new RuntimeException(
                '平台改写失败：'.OpenAiRuntimeProvider::normalizeApiException($exception, $providerUrl),
                0,
                $exception,
            );
        }

        AiModel::query()->whereKey($model->id)->update([
            'used_today' => DB::raw('COALESCE(used_today, 0) + 1'),
            'total_used' => DB::raw('COALESCE(total_used, 0) + 1'),
            'updated_at' => now(),
        ]);

        return [...$result, 'model' => (string) $model->model_id, 'source' => 'laravel_ai_sdk'];
    }

    private function resolveModel(Article $article): AiModel
    {
        $taskModel = $article->task?->aiModel;
        if ($taskModel instanceof AiModel && $taskModel->status === 'active' && $taskModel->model_type !== 'embedding') {
            return $taskModel;
        }

        $model = AiModel::query()
            ->where('status', 'active')
            ->where(fn ($query) => $query->whereNull('model_type')->orWhere('model_type', '!=', 'embedding'))
            ->orderBy('failover_priority')
            ->orderBy('id')
            ->first();

        if (! $model) {
            throw new RuntimeException('没有可用的平台改写模型，请先启用一个非 Embedding 模型。');
        }

        return $model;
    }

    private function instructions(): string
    {
        return <<<'PROMPT'
你是 GEOFlow 的中文多平台内容改写 Agent。基于源文章改写，不得增加源文没有的事实、数字、案例、引语或承诺。保留核心观点与事实边界，但标题、开篇、结构和语气必须符合目标平台规则。全文使用简体中文。
源文章是厂商官方网站的第一方内容。改写到外部内容平台时，必须切换为平台规则指定的编辑视角，不得照搬官网的“我们”口吻，不得假装平台账号就是产品厂商；需要提及厂商时使用品牌名称或“该品牌”。官网内链不应批量保留，只有平台规则明确允许的来源链接才可保留。
只输出合法 JSON 对象，不要输出代码围栏或解释。JSON 字段必须为 title、excerpt、content、tags、image_requirements；tags 和 image_requirements 必须是字符串数组。
PROMPT;
    }

    private function prompt(Article $article, string $platform, array $rules): string
    {
        $source = mb_substr(strip_tags((string) $article->content), 0, 24000);
        $encodedRules = json_encode($rules, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return "目标平台：{$platform}\n平台规则：{$encodedRules}\n\n"
            ."源标题：{$article->title}\n源摘要：{$article->excerpt}\n源正文：\n{$source}";
    }

    /** @return array{title: string, excerpt: string, content: string, tags: array<int, string>, image_requirements: array<int, string>} */
    private function decode(string $response, array $rules): array
    {
        $normalized = trim($response);
        $normalized = preg_replace('/^```(?:json)?\s*|\s*```$/u', '', $normalized) ?: $normalized;

        try {
            $data = json_decode($normalized, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('模型未返回合法的平台稿件 JSON。', 0, $exception);
        }

        if (! is_array($data) || trim((string) ($data['title'] ?? '')) === '' || trim((string) ($data['content'] ?? '')) === '') {
            throw new RuntimeException('模型返回的平台稿件缺少标题或正文。');
        }

        $title = trim((string) $data['title']);
        $content = trim((string) $data['content']);
        $titleMax = max(1, (int) ($rules['title_max_chars'] ?? 60));
        $contentMin = max(1, (int) ($rules['content_min_chars'] ?? 500));
        $contentMax = max($contentMin, (int) ($rules['content_max_chars'] ?? 5000));
        $contentLength = Str::length(strip_tags($content));
        if (Str::length($title) > $titleMax) {
            throw new RuntimeException("平台稿件标题超过 {$titleMax} 字限制。");
        }
        if ($contentLength < $contentMin || $contentLength > $contentMax) {
            throw new RuntimeException("平台稿件正文应在 {$contentMin} 至 {$contentMax} 字之间。");
        }

        return [
            'title' => $title,
            'excerpt' => trim((string) ($data['excerpt'] ?? '')),
            'content' => $content,
            'tags' => $this->normalizeList((array) ($data['tags'] ?? []), 10, 40),
            'image_requirements' => $this->normalizeList((array) ($data['image_requirements'] ?? []), 10, 200),
        ];
    }

    /** @param array<int|string, mixed> $items @return array<int, string> */
    private function normalizeList(array $items, int $maxItems, int $maxLength): array
    {
        return array_values(array_slice(array_filter(array_map(
            static fn (mixed $item): string => mb_substr(trim(is_scalar($item) ? (string) $item : ''), 0, $maxLength),
            $items,
        )), 0, $maxItems));
    }
}
