<?php

namespace App\Services\GeoFlow;

use App\Models\ContentVariant;
use Illuminate\Support\Str;

final class ContentVariantPublicationPackBuilder
{
    /** @param array<string, mixed> $rules @param array<string, mixed> $qualityCheck @return array<string, mixed> */
    public function build(ContentVariant $variant, array $rules, array $qualityCheck): array
    {
        $imageRules = (array) ($rules['image_rules'] ?? []);
        $tags = array_values(array_filter((array) $variant->tags));
        $plainContent = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $variant->content)) ?? '');

        return [
            'version' => $variant->version,
            'platform' => $variant->platform,
            'platform_label' => (string) ($rules['label'] ?? $variant->platform),
            'title' => (string) $variant->title,
            'summary' => (string) $variant->excerpt,
            'content' => (string) $variant->content,
            'plain_content' => $plainContent,
            'tags' => $tags,
            'cover' => [
                'required' => (bool) ($imageRules['cover_required'] ?? false),
                'recommended_ratio' => (string) ($imageRules['cover_ratio'] ?? '16:9'),
                'brief' => (string) ($imageRules['cover_brief'] ?? '使用与文章主题一致、可商用或已获授权的封面图。'),
            ],
            'inline_images' => [
                'minimum' => (int) ($imageRules['inline_min'] ?? 0),
                'briefs' => array_values((array) $variant->image_requirements),
            ],
            'paste_checklist' => [
                '核对标题、摘要与正文未包含未验证事实。',
                '上传已获授权的封面及正文配图。',
                '确认标签、署名和来源披露符合账号当前后台要求。',
                '粘贴后检查排版、链接和敏感词提示，再手动提交发布。',
            ],
            'readiness' => $qualityCheck,
            'generated_at' => now()->toIso8601String(),
        ];
    }
}
