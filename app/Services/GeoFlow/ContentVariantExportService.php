<?php

namespace App\Services\GeoFlow;

use App\Models\ContentGroup;
use App\Models\ContentVariant;
use App\Support\Admin\WeChatArticleHtmlExporter;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

final class ContentVariantExportService
{
    public function __construct(private readonly WeChatArticleHtmlExporter $weChatExporter) {}

    /** @param array<int, int> $variantIds */
    public function export(ContentGroup $contentGroup, array $variantIds): BinaryFileResponse
    {
        $variants = ContentVariant::query()
            ->where('content_group_id', $contentGroup->id)
            ->whereIn('id', $variantIds)
            ->with(['versions' => fn ($query) => $query->orderByDesc('version')])
            ->get();

        if ($variants->count() !== count($variantIds)) {
            throw new RuntimeException('部分平台版本不存在，无法导出。');
        }
        if ($variants->contains(fn (ContentVariant $variant): bool => $variant->review_status !== ContentVariant::REVIEW_APPROVED || $variant->status !== ContentVariant::STATUS_READY)) {
            throw new RuntimeException('只能导出已审核通过的平台版本。');
        }

        $directory = storage_path('app/tmp');
        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException('无法创建导出临时目录。');
        }
        $path = tempnam($directory, 'content-variants-');
        if ($path === false) {
            throw new RuntimeException('无法创建导出文件。');
        }
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('无法创建导出压缩包。');
        }

        $manifest = [];
        foreach ($variants as $variant) {
            $version = $variant->versions->firstWhere('version', $variant->version);
            if (! $version) {
                $zip->close();
                throw new RuntimeException('平台版本历史不完整，无法导出。');
            }
            $slug = Str::slug($variant->platform) ?: 'platform-'.$variant->id;
            $markdown = '# '.$version->title."\n\n";
            if ($version->excerpt) {
                $markdown .= '> '.$version->excerpt."\n\n";
            }
            $markdown .= $version->content."\n";
            if ($version->tags) {
                $markdown .= "\n---\n标签：".implode('、', $version->tags)."\n";
            }
            $zip->addFromString($slug.'/article.md', $markdown);
            if ($variant->platform === 'wechat_official') {
                $zip->addFromString($slug.'/article.html', $this->weChatExporter->toHtml($version->content));
            }
            $zip->addFromString($slug.'/metadata.json', json_encode([
                'platform' => $variant->platform,
                'version' => $version->version,
                'title' => $version->title,
                'excerpt' => $version->excerpt,
                'tags' => $version->tags,
                'image_requirements' => $version->image_requirements,
                'reviewed_at' => $variant->reviewed_at?->toIso8601String(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}');
            $manifest[] = ['platform' => $variant->platform, 'version' => $version->version, 'file' => $slug.'/article.md'];
        }
        $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]');
        $zip->close();

        $filename = 'content-group-'.$contentGroup->id.'-'.now()->format('Ymd-His').'.zip';

        return response()->download($path, $filename, ['Content-Type' => 'application/zip'])->deleteFileAfterSend(true);
    }
}
