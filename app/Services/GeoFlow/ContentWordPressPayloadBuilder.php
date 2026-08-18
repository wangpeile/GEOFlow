<?php

namespace App\Services\GeoFlow;

use App\Models\Article;
use App\Models\ContentVariantVersion;
use App\Support\Site\ArticleHtmlPresenter;

final class ContentWordPressPayloadBuilder
{
    public function __construct(private readonly DistributionPayloadBuilder $baseBuilder) {}

    /** @return array<string, mixed> */
    public function build(Article $article, ContentVariantVersion $version, string $mode, ?string $scheduledFor): array
    {
        $payload = $this->baseBuilder->build($article);
        $body = ArticleHtmlPresenter::stripLeadingTitleHeading((string) $version->content, (string) $version->title);
        $payload['event'] = 'content.wordpress.publish';
        $payload['article'] = array_replace($payload['article'], [
            'title' => (string) $version->title,
            'excerpt' => (string) ($version->excerpt ?? ''),
            'content' => (string) $version->content,
            'content_html' => ArticleHtmlPresenter::markdownToHtml($body),
            'keywords' => implode(',', $version->tags ?? []),
        ]);
        $payload['publication'] = [
            'mode' => $mode,
            'wordpress_status' => match ($mode) {
                'immediate' => 'publish',
                'scheduled' => 'future',
                default => 'draft',
            },
            'scheduled_for' => $scheduledFor,
            'content_variant_version_id' => (int) $version->id,
            'content_variant_version' => (int) $version->version,
        ];

        return $payload;
    }
}
