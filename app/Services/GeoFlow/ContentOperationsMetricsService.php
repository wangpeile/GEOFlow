<?php

namespace App\Services\GeoFlow;

use App\Models\ArticleDistribution;
use App\Models\ContentProduction;
use App\Models\ContentVariant;
use App\Models\QualityReport;
use Carbon\CarbonImmutable;

final class ContentOperationsMetricsService
{
    /** @return array<string,mixed> */
    public function summarize(?string $from = null, ?string $to = null): array
    {
        $fromAt = $from ? CarbonImmutable::parse($from)->startOfDay() : now()->subDays(29)->startOfDay();
        $toAt = $to ? CarbonImmutable::parse($to)->endOfDay() : now()->endOfDay();

        $productions = ContentProduction::query()->whereBetween('created_at', [$fromAt, $toAt]);
        $quality = QualityReport::query()->whereBetween('created_at', [$fromAt, $toAt]);
        $variants = ContentVariant::query()->whereBetween('created_at', [$fromAt, $toAt]);
        $distributions = ArticleDistribution::query()->whereBetween('created_at', [$fromAt, $toAt]);

        return [
            'period' => ['from' => $fromAt->toDateString(), 'to' => $toAt->toDateString(), 'timezone' => config('app.timezone')],
            'productions' => [
                'total' => (clone $productions)->count(),
                'by_status' => $this->groupCounts(clone $productions, 'status'),
            ],
            'quality' => [
                'reports' => (clone $quality)->count(),
                'by_status' => $this->groupCounts(clone $quality, 'status'),
            ],
            'platform_variants' => [
                'total' => (clone $variants)->count(),
                'approved' => (clone $variants)->where('review_status', 'approved')->count(),
                'by_platform' => $this->groupCounts(clone $variants, 'platform'),
            ],
            'publishing' => [
                'total' => (clone $distributions)->count(),
                'by_status' => $this->groupCounts(clone $distributions, 'status'),
            ],
            'recent' => (clone $productions)->select(['id', 'uuid', 'name', 'topic', 'status', 'article_id', 'created_at'])
                ->latest()->limit(20)->get()->map(fn (ContentProduction $production): array => [
                    'production_id' => (int) $production->id,
                    'production_uuid' => (string) $production->uuid,
                    'article_id' => $production->article_id ? (int) $production->article_id : null,
                    'name' => (string) $production->name,
                    'topic' => (string) $production->topic,
                    'status' => $production->status->value,
                    'created_at' => $production->created_at?->toISOString(),
                ])->all(),
        ];
    }

    /** @return array<string,int> */
    private function groupCounts($query, string $column): array
    {
        return $query->selectRaw("{$column}, COUNT(*) AS aggregate")
            ->groupBy($column)->pluck('aggregate', $column)
            ->map(fn ($count): int => (int) $count)->all();
    }
}
