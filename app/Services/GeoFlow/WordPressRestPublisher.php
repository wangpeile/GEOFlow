<?php

namespace App\Services\GeoFlow;

use App\Models\ArticleDistribution;
use App\Models\DistributionChannel;
use Illuminate\Http\Client\Response;
use RuntimeException;

class WordPressRestPublisher implements DistributionPublisherInterface
{
    public function __construct(
        private readonly WordPressRestRequestFactory $requestFactory,
        private readonly WordPressMediaSyncService $mediaSyncService,
        private readonly WordPressTaxonomySyncService $taxonomySyncService,
    ) {}

    public function health(DistributionChannel $channel): array
    {
        $indexResponse = $this->requestFactory->request($channel, 10)->get($channel->wordpressRestBaseUrl());
        $this->throwIfFailed($indexResponse, 'WordPress REST 入口检测');

        if ($channel->resolvedChannelConfig()['wordpress_health_strategy'] === 'posts_context_edit') {
            return $this->postsContextEditHealth($channel);
        }

        $response = $this->requestFactory->request($channel, 10)
            ->get($channel->wordpressRestBaseUrl().'/wp/v2/users/me', ['context' => 'edit']);
        $this->throwIfFailed($response, 'WordPress 健康检查');
        $user = $response->json();
        if (! is_array($user)) {
            $user = [];
        }
        $capabilities = is_array($user['capabilities'] ?? null) ? $user['capabilities'] : [];

        return [
            'ok' => true,
            'channel_type' => 'wordpress_rest',
            'rest_base_url' => $channel->wordpressRestBaseUrl(),
            'user_id' => (int) ($user['id'] ?? 0),
            'user_name' => (string) ($user['name'] ?? ''),
            'can_edit_posts' => (bool) ($capabilities['edit_posts'] ?? false),
            'can_publish_posts' => (bool) ($capabilities['publish_posts'] ?? false),
            'can_upload_files' => (bool) ($capabilities['upload_files'] ?? false),
        ];
    }

    public function publish(ArticleDistribution $distribution, array $payload): array
    {
        $distribution->loadMissing('channel');
        $channel = $this->channel($distribution);
        $existingPostId = $this->recoverPostIdForRetry($distribution, $channel, $payload);
        if ($existingPostId !== null) {
            $distribution->forceFill(['remote_id' => (string) $existingPostId])->save();

            return $this->update($distribution, $payload);
        }
        $response = $this->requestFactory->request($channel)
            ->post($channel->wordpressRestBaseUrl().'/wp/v2/posts', $this->postPayload($channel, $payload));
        $this->throwIfFailed($response, 'WordPress 文章发布');

        return $this->postResult($channel, $response, $payload);
    }

    public function update(ArticleDistribution $distribution, array $payload): array
    {
        $distribution->loadMissing('channel');
        $channel = $this->channel($distribution);
        $postId = $distribution->wordpressPostId();
        if (! $postId) {
            return $this->publish($distribution, $payload);
        }

        $request = $this->requestFactory->request($channel);
        $endpoint = $channel->wordpressRestBaseUrl().'/wp/v2/posts/'.$postId;
        $response = $channel->resolvedChannelConfig()['wordpress_update_method'] === 'put'
            ? $request->put($endpoint, $this->postPayload($channel, $payload))
            : $request->post($endpoint, $this->postPayload($channel, $payload));
        $this->throwIfFailed($response, 'WordPress 文章更新');

        return $this->postResult($channel, $response, $payload);
    }

    public function delete(ArticleDistribution $distribution): array
    {
        $distribution->loadMissing('channel');
        $channel = $this->channel($distribution);
        $postId = $distribution->wordpressPostId();
        if (! $postId) {
            return [
                'deleted' => true,
                'remote_id' => null,
                'remote_url' => null,
                'message' => 'missing_remote_post_id',
            ];
        }

        $response = $this->requestFactory->request($channel)
            ->delete($channel->wordpressRestBaseUrl().'/wp/v2/posts/'.$postId, ['force' => false]);
        $this->throwIfFailed($response, 'WordPress 文章删除');

        return [
            'deleted' => true,
            'remote_id' => (string) $postId,
            'remote_url' => null,
        ];
    }

    public function syncSiteSettings(
        DistributionChannel $channel,
        ?string $idempotencyKey = null,
        ?array $settings = null,
    ): array
    {
        if (! $channel->resolvedChannelConfig()['wordpress_site_settings_sync_enabled']) {
            throw new RuntimeException('当前 WordPress 渠道账号未授权站点设置同步。');
        }

        $settings ??= $channel->resolvedSiteSettings();
        $payload = [
            'title' => $settings['site_name'],
            'description' => $settings['site_description'],
            'posts_per_page' => $settings['per_page'],
        ];

        $request = $this->requestFactory->request($channel);
        if (is_string($idempotencyKey) && $idempotencyKey !== '') {
            $request = $request->withHeaders(['Idempotency-Key' => $idempotencyKey]);
        }
        $response = $request->post($channel->wordpressRestBaseUrl().'/wp/v2/settings', $payload);
        $this->throwIfFailed($response, 'WordPress 站点设置同步');

        return [
            'ok' => true,
            'settings' => $payload,
        ];
    }

    /**
     * @param  array<string,mixed>  $payload
     * @return array<string,mixed>
     */
    private function postPayload(DistributionChannel $channel, array $payload): array
    {
        $article = is_array($payload['article'] ?? null) ? $payload['article'] : [];
        $publication = is_array($payload['publication'] ?? null) ? $payload['publication'] : [];
        $config = $channel->resolvedChannelConfig();
        $contentHtml = (string) ($article['content_html'] ?? '');

        if ($config['wordpress_image_strategy'] === 'upload_to_media') {
            $contentHtml = $this->mediaSyncService->rewriteContentImages($channel, $payload, $contentHtml);
        }

        $postPayload = [
            'title' => (string) ($article['title'] ?? ''),
            'slug' => (string) ($article['slug'] ?? ''),
            'status' => (string) ($publication['wordpress_status'] ?? $config['wordpress_post_status']),
            'content' => $contentHtml,
            'excerpt' => (string) ($article['excerpt'] ?? ''),
        ];
        if ($postPayload['status'] === 'future' && filled($publication['scheduled_for'] ?? null)) {
            $postPayload['date'] = (string) $publication['scheduled_for'];
        }

        $categoryIds = $this->taxonomySyncService->categoryIds($channel, $payload);
        if ($categoryIds !== []) {
            $postPayload['categories'] = $categoryIds;
        }

        $tagIds = $this->taxonomySyncService->tagIds($channel, $payload);
        if ($tagIds !== []) {
            $postPayload['tags'] = $tagIds;
        }

        $featuredMediaId = $this->mediaSyncService->uploadHeroImage($channel, $payload);
        if ($featuredMediaId !== null) {
            $postPayload['featured_media'] = $featuredMediaId;
        }

        return $postPayload;
    }

    /** @param array<string,mixed> $payload */
    private function recoverPostIdForRetry(ArticleDistribution $distribution, DistributionChannel $channel, array $payload): ?int
    {
        if ($distribution->wordpressPostId() || $distribution->attempt_count < 2) {
            return null;
        }
        $article = is_array($payload['article'] ?? null) ? $payload['article'] : [];
        $slug = trim((string) ($article['slug'] ?? ''));
        if ($slug === '') {
            throw new RuntimeException('WordPress 重试缺少稳定 slug，已阻止重复创建文章。');
        }
        $response = $this->requestFactory->request($channel)
            ->get($channel->wordpressRestBaseUrl().'/wp/v2/posts', [
                'slug' => $slug,
                'context' => 'edit',
                'status' => 'any',
                'per_page' => 1,
            ]);
        $this->throwIfFailed($response, 'WordPress 重试身份确认');
        $posts = $response->json();
        $postId = is_array($posts) && isset($posts[0]['id']) ? (int) $posts[0]['id'] : 0;

        return $postId > 0 ? $postId : null;
    }

    private function channel(ArticleDistribution $distribution): DistributionChannel
    {
        if (! $distribution->channel instanceof DistributionChannel) {
            throw new RuntimeException('分发记录缺少 WordPress 渠道。');
        }

        return $distribution->channel;
    }

    private function throwIfFailed(Response $response, string $operation): void
    {
        if (! $response->failed()) {
            return;
        }

        throw new RuntimeException($operation.'失败：HTTP '.$response->status());
    }

    /**
     * @return array<string,mixed>
     */
    private function postResult(DistributionChannel $channel, Response $response, array $payload): array
    {
        $json = $response->json();
        if (! is_array($json)) {
            throw new RuntimeException('WordPress 返回内容不是有效 JSON。');
        }

        $postId = (int) ($json['id'] ?? 0);
        if ($postId <= 0) {
            throw new RuntimeException('WordPress 返回结果缺少有效文章 ID。');
        }

        $this->syncRankMathMeta($channel, $postId, $payload);

        return [
            'remote_id' => (string) $postId,
            'remote_url' => (string) ($json['link'] ?? ''),
            'remote_meta' => [
                'wordpress_post_id' => $postId,
            ],
        ];
    }

    /** @param array<string,mixed> $payload */
    private function syncRankMathMeta(DistributionChannel $channel, int $postId, array $payload): void
    {
        if (! $channel->resolvedChannelConfig()['wordpress_rank_math_enabled']) {
            return;
        }

        $article = is_array($payload['article'] ?? null) ? $payload['article'] : [];
        $title = trim((string) ($article['title'] ?? ''));
        $description = trim((string) ($article['meta_description'] ?? $article['excerpt'] ?? ''));
        $focusKeyword = trim((string) ($article['keywords'] ?? ''));
        $fields = array_filter([
            'rank_math_focus_keyword' => $focusKeyword,
            'rank_math_title' => $title,
            'rank_math_description' => $description,
        ], static fn (string $value): bool => $value !== '');

        foreach ($fields as $key => $value) {
            $response = $this->requestFactory->request($channel)
                ->post(rtrim($channel->wordpressRestBaseUrl(), '/').'/rankmath/v1/updateMeta', [
                    'objectID' => $postId,
                    'objectType' => 'post',
                    'metaKey' => $key,
                    'metaValue' => $value,
                ]);
            $this->throwIfFailed($response, 'Rank Math SEO 写入');
        }
    }

    /** @return array<string,mixed> */
    private function postsContextEditHealth(DistributionChannel $channel): array
    {
        $response = $this->requestFactory->request($channel, 10)
            ->get($channel->wordpressRestBaseUrl().'/wp/v2/posts', [
                'context' => 'edit',
                'status' => 'draft',
                'per_page' => 1,
            ]);
        $this->throwIfFailed($response, 'WordPress 文章编辑权限检测');

        return [
            'ok' => true,
            'channel_type' => 'wordpress_rest',
            'rest_base_url' => $channel->wordpressRestBaseUrl(),
            'health_strategy' => 'posts_context_edit',
            'can_edit_posts' => true,
            'can_publish_posts' => null,
            'can_upload_files' => null,
        ];
    }
}
