<?php

namespace App\Services\GeoFlow;

use Illuminate\Support\Str;

final class ContentVariantQualityChecker
{
    /**
     * @param  array<string, mixed>  $rules
     * @param  array<string, mixed>  $factCheck
     * @return array{passed: bool, blockers: array<int, string>, warnings: array<int, string>, metrics: array<string, int|float>, checks: array<string, bool>, checked_at: string}
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
        $validation = (array) ($rules['validation'] ?? []);
        $checks = [];

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
        $checks['title_length'] = $titleLength > 0 && $titleLength <= (int) ($rules['title_max_chars'] ?? 500);
        if ($contentLength < (int) ($rules['content_min_chars'] ?? 1)) {
            $blockers[] = '正文未达到目标平台建议的最少字数。';
        }
        if ($contentLength > (int) ($rules['content_max_chars'] ?? 100000)) {
            $blockers[] = '正文超过目标平台建议的最多字数。';
        }
        $checks['content_length'] = $contentLength >= (int) ($rules['content_min_chars'] ?? 1)
            && $contentLength <= (int) ($rules['content_max_chars'] ?? 100000);
        if (! ($factCheck['passed'] ?? false)) {
            $blockers[] = '正文包含源文章之外的数字、日期或链接。';
        }
        $checks['fact_boundary'] = (bool) ($factCheck['passed'] ?? false);
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

        $paragraphs = array_values(array_filter(array_map(
            static fn (string $paragraph): string => trim(strip_tags($paragraph)),
            preg_split('/\n\s*\n|<\/p>/iu', $content) ?: [],
        ), static fn ($item) => $item !== ''));
        $maxParagraphChars = (int) ($validation['max_paragraph_chars'] ?? 0);
        if ($maxParagraphChars > 0 && collect($paragraphs)->contains(fn ($paragraph) => Str::length(trim($paragraph)) > $maxParagraphChars)) {
            $warnings[] = "存在超过 {$maxParagraphChars} 字的段落，建议拆分后再粘贴发布。";
            $checks['paragraph_length'] = false;
        } else {
            $checks['paragraph_length'] = true;
        }

        $tagMin = (int) ($validation['tag_min'] ?? 0);
        $tagMax = (int) ($validation['tag_max'] ?? 20);
        if (count($tags) < $tagMin) {
            $blockers[] = "平台要求至少 {$tagMin} 个标签。";
        }
        if (count($tags) > $tagMax) {
            $blockers[] = "平台最多允许 {$tagMax} 个标签。";
        }
        $checks['tags'] = count($tags) >= $tagMin && count($tags) <= $tagMax;

        $forbidden = array_values(array_filter((array) ($validation['forbidden_patterns'] ?? [])));
        $foundForbidden = array_values(array_filter($forbidden, static fn ($pattern) => Str::contains($title.' '.$plainContent, $pattern)));
        if ($foundForbidden !== []) {
            $blockers[] = '检测到平台规则禁止或需人工确认的表达：'.implode('、', $foundForbidden).'。';
        }
        $checks['forbidden_patterns'] = $foundForbidden === [];

        $imageRules = (array) ($rules['image_rules'] ?? []);
        $requiresCover = (bool) ($imageRules['cover_required'] ?? false);
        if ($requiresCover && $imageRequirements === []) {
            $warnings[] = '该平台建议准备封面和正文配图；发布包会列出素材要求。';
        }
        $checks['asset_brief'] = ! $requiresCover || $imageRequirements !== [];

        return [
            'passed' => $blockers === [],
            'blockers' => $blockers,
            'warnings' => $warnings,
            'metrics' => [
                'title_chars' => $titleLength,
                'content_chars' => $contentLength,
                'chinese_chars' => $hanCount,
                'latin_chars' => $latinCount,
                'tag_count' => count($tags),
                'paragraph_count' => count($paragraphs),
            ],
            'checks' => $checks,
            'checked_at' => now()->toIso8601String(),
        ];
    }
}
