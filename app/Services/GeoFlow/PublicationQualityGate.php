<?php

namespace App\Services\GeoFlow;

use App\Enums\QualityReportStatus;
use App\Exceptions\ContentQualityGateException;
use App\Models\Article;
use App\Models\ContentProduction;

final class PublicationQualityGate
{
    public function check(Article $article): void
    {
        $production = ContentProduction::query()
            ->where('article_id', $article->id)
            ->latest('id')
            ->first();
        if (! $production) {
            return;
        }

        $version = $production->articleVersions()->first();
        if (! $version || ! $this->matchesArticle($version, $article)) {
            throw new ContentQualityGateException('文章已发生修改，请重新生成版本并执行质量检查。');
        }

        $report = $production->qualityReports()
            ->where('article_version_id', $version->id)
            ->latest('version')
            ->first();
        if (! $report) {
            throw new ContentQualityGateException('文章尚未完成质量检查，不能发布。');
        }
        if ($report->status === QualityReportStatus::Blocked) {
            throw new ContentQualityGateException('文章仍有质量阻断项，不能发布。');
        }
    }

    private function matchesArticle(object $version, Article $article): bool
    {
        return (string) $version->title === (string) $article->title
            && (string) $version->summary === (string) $article->excerpt
            && (string) $version->body === (string) $article->content
            && (string) $version->meta_description === (string) $article->meta_description;
    }
}
