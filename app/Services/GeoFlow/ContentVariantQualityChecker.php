<?php

namespace App\Services\GeoFlow;

use Illuminate\Support\Str;

final class ContentVariantQualityChecker
{
    /**
     * @param  array<string, mixed>  $rules
     * @param  array<string, mixed>  $factCheck
     * @return array{passed: bool, blockers: array<int, string>, warnings: array<int, string>, metrics: array<string, int|float>, checked_at: string}
     */
    public function inspect(
        string $title,
        string $content,
        array $rules,
        array $factCheck,
        ?string $excerpt = null,
        array $tags = [],
        array $imageRequirements = [],
    ): array {
        $plainContent = trim(preg_replace('/\s+/u', ' ', strip_tags($content)) ?? '');
        $titleLength = Str::length(trim($title));
        $contentLength = Str::length($plainContent);
        preg_match_all('/[\x{4e00}-\x{9fff}]/u', $plainContent, $hanMatches);
        preg_match_all('/[A-Za-z]/u', $plainContent, $latinMatches);
        $hanCount = count($hanMatches[0] ?? []);
        $latinCount = count($latinMatches[0] ?? []);
        $blockers = [];
        $warnings = [];

        if ($titleLength === 0) {
            $blockers[] = '标题不能为空。';
        }
        if ($contentLength === 0) {
            $blockers[] = '正文不能为空。';
        }
        if (trim((string) $excerpt) === '') {
            $blockers[] = '摘要不能为空。';
        }
        if ($titleLength > (int) ($rules['title_max_chars'] ?? 500)) {
            $blockers[] = '标题超过目标平台字数限制。';
        }
        if ($contentLength < (int) ($rules['content_min_chars'] ?? 1)) {
            $blockers[] = '正文未达到目标平台建议的最少字数。';
        }
        if ($contentLength > (int) ($rules['content_max_chars'] ?? 100000)) {
            $blockers[] = '正文超过目标平台建议的最多字数。';
        }
        if (! ($factCheck['passed'] ?? false)) {
            $blockers[] = '正文包含源文章之外的数字、日期或链接。';
        }
        if ($contentLength > 0 && $hanCount < 20) {
            $blockers[] = '正文缺少足够的中文内容。';
        } elseif ($latinCount > $hanCount) {
            $warnings[] = '正文中的英文字母较多，请确认仅保留必要的品牌名和技术术语。';
        }
        if ($tags === []) {
            $warnings[] = '尚未设置平台标签。';
        }
        if ($imageRequirements === []) {
            $warnings[] = '尚未设置配图要求。';
        }

        return [
            'passed' => $blockers === [],
            'blockers' => $blockers,
            'warnings' => $warnings,
            'metrics' => [
                'title_chars' => $titleLength,
                'content_chars' => $contentLength,
                'chinese_chars' => $hanCount,
                'latin_chars' => $latinCount,
            ],
            'checked_at' => now()->toIso8601String(),
        ];
    }
}
