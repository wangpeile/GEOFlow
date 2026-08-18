<?php

namespace App\Services\GeoFlow;

use App\Ai\Agents\MarkdownContentWriterAgent;
use App\Contracts\GeoFlow\ContentEditorAssistant;
use App\Models\AiModel;
use App\Models\Article;
use App\Support\GeoFlow\ApiKeyCrypto;
use App\Support\GeoFlow\OpenAiRuntimeProvider;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class LaravelAiContentEditorAssistant implements ContentEditorAssistant
{
    public function __construct(
        private readonly ApiKeyCrypto $apiKeyCrypto,
        private readonly ChineseContentGuard $contentGuard,
    ) {}

    public function assist(Article $article, string $action, string $selection, ?string $instruction = null): array
    {
        $model = AiModel::query()
            ->where('status', 'active')
            ->where(fn ($query) => $query->whereNull('model_type')->orWhere('model_type', '!=', 'embedding'))
            ->orderBy('failover_priority')->orderBy('id')->first();
        if (! $model) {
            throw new RuntimeException('没有可用的中文写作模型。');
        }

        $providerUrl = OpenAiRuntimeProvider::resolveChatBaseUrl((string) $model->api_url);
        $apiKey = $this->apiKeyCrypto->decrypt((string) $model->getRawOriginal('api_key'));
        if ($providerUrl === '' || $apiKey === '') {
            throw new RuntimeException('写作模型的 API 地址或密钥未配置。');
        }

        $provider = OpenAiRuntimeProvider::registerProvider(
            'content_editor_assistant',
            OpenAiRuntimeProvider::resolveChatDriver($providerUrl, (string) $model->model_id),
            $providerUrl,
            $apiKey,
        );
        $agent = new MarkdownContentWriterAgent($this->instructions($action), maxTokens: 4096);

        try {
            $response = $agent->prompt($this->prompt($article, $selection, $instruction), [], $provider, (string) $model->model_id);
            $text = trim(OpenAiRuntimeProvider::normalizeGeneratedText((string) ($response->text ?? '')));
            $text = preg_replace('/^```(?:markdown|text)?\s*|\s*```$/u', '', $text) ?: $text;
            $this->contentGuard->validateField('result', $text);
        } catch (Throwable $exception) {
            throw new RuntimeException('编辑辅助失败：'.OpenAiRuntimeProvider::normalizeApiException($exception, $providerUrl), 0, $exception);
        }

        AiModel::query()->whereKey($model->id)->update([
            'used_today' => DB::raw('COALESCE(used_today, 0) + 1'),
            'total_used' => DB::raw('COALESCE(total_used, 0) + 1'),
            'updated_at' => now(),
        ]);

        return ['text' => $text, 'model' => (string) $model->model_id, 'source' => 'laravel_ai_sdk'];
    }

    private function instructions(string $action): string
    {
        $task = match ($action) {
            'expand' => '扩写选中文字，补充有用细节，但不得虚构事实。',
            'rewrite' => '改写选中文字，使表达自然、清晰、专业。',
            'title' => '根据选中文字生成一个准确有吸引力的标题。',
            'description' => '根据选中文字生成简洁的文章摘要或元描述。',
            'paragraph' => '把选中文字整理成结构完整、衔接自然的段落。',
            'faq' => '根据选中文字生成常见问题与简洁回答。',
            'outline' => '根据选中文字生成层级清晰的 Markdown 大纲。',
            default => throw new RuntimeException('不支持的编辑辅助动作。'),
        };

        return "你是 GEOFlow 中文编辑助手。{$task}全文只用简体中文。只输出用于替换选区的最终文本，不输出解释、代码围栏或思考过程。保留原文事实边界、数字、专有名词和链接。";
    }

    private function prompt(Article $article, string $selection, ?string $instruction): string
    {
        $extra = trim((string) $instruction);

        return "文章标题：{$article->title}\n用户补充要求：".($extra !== '' ? $extra : '无')."\n\n需要处理的选区：\n{$selection}";
    }
}
