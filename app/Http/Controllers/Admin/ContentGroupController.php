<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GenerateContentVariantsRequest;
use App\Models\Article;
use App\Models\ContentGroup;
use App\Models\ContentVariant;
use App\Models\DistributionChannel;
use App\Services\GeoFlow\ContentGroupService;
use App\Services\GeoFlow\ContentVariantGenerationService;
use App\Services\GeoFlow\ContentWordPressPublicationService;
use App\Support\AdminWeb;
use App\Support\GeoFlow\ContentPlatformCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use RuntimeException;

class ContentGroupController extends Controller
{
    public function __construct(
        private readonly ContentGroupService $contentGroupService,
        private readonly ContentVariantGenerationService $variantGenerationService,
        private readonly ContentPlatformCatalog $platformCatalog,
        private readonly ContentWordPressPublicationService $wordPressPublicationService,
    ) {}

    public function index(): View
    {
        $contentGroups = ContentGroup::query()
            ->select(['id', 'main_article_id', 'task_id', 'name', 'status', 'created_at', 'updated_at'])
            ->with([
                'mainArticle:id,title,status,review_status',
                'task:id,name',
            ])
            ->withCount([
                'variants',
                'variants as ready_variants_count' => fn ($query) => $query->where('status', 'ready'),
                'variants as published_variants_count' => fn ($query) => $query->where('status', 'published'),
            ])
            ->latest()
            ->paginate((int) config('geoflow.admin_items_per_page', 20));

        return view('admin.content-groups.index', [
            'pageTitle' => __('admin.content_groups.page_title'),
            'activeMenu' => 'content_production',
            'adminSiteName' => AdminWeb::siteName(),
            'contentGroups' => $contentGroups,
            'availableArticles' => Article::query()
                ->select(['id', 'title', 'created_at'])
                ->whereDoesntHave('contentGroup')
                ->latest()
                ->limit(100)
                ->get(),
        ]);
    }

    public function store(Article $article): RedirectResponse
    {
        $contentGroup = $this->contentGroupService->ensureForArticle($article);

        return redirect()
            ->route('admin.content-groups.show', $contentGroup)
            ->with('message', __('admin.content_groups.message.created'));
    }

    public function show(ContentGroup $contentGroup): View
    {
        $contentGroup->load([
            'mainArticle:id,title,slug,excerpt,content,keywords,category_id,status,review_status,updated_at',
            'task:id,name',
            'variants' => fn ($query) => $query->select([
                'id', 'content_group_id', 'source_article_id', 'platform', 'title', 'excerpt', 'content',
                'tags', 'image_requirements', 'status', 'review_status', 'version', 'template_version',
                'generation_meta', 'source_content_hash', 'fact_check', 'failure_message',
                'quality_check', 'reviewed_by', 'reviewed_at', 'review_note',
                'published_url', 'published_at', 'updated_at',
            ]),
            'variants.latestPublication' => fn ($query) => $query->select([
                'article_distributions.id', 'article_distributions.article_id',
                'article_distributions.distribution_channel_id', 'article_distributions.content_variant_id',
                'article_distributions.content_variant_version_id', 'article_distributions.status',
                'article_distributions.publication_mode', 'article_distributions.scheduled_for',
                'article_distributions.published_version', 'article_distributions.published_at',
                'article_distributions.remote_id', 'article_distributions.remote_url',
                'article_distributions.last_error_message', 'article_distributions.attempt_count',
                'article_distributions.updated_at',
            ])->with('channel:id,name,domain'),
            'variants.versions' => fn ($query) => $query->select([
                'id', 'content_variant_id', 'version', 'change_type', 'title', 'template_version', 'generation_meta', 'quality_check',
                'created_by', 'created_at',
            ])->with('creator:id,username'),
            'variants.reviewer:id,username',
            'variants.reviews' => fn ($query) => $query->select([
                'id', 'content_variant_id', 'content_variant_version_id', 'reviewer_id', 'decision', 'note', 'created_at',
            ])->with('reviewer:id,username')->latest('id'),
        ]);

        return view('admin.content-groups.show', [
            'pageTitle' => __('admin.content_groups.detail_title'),
            'activeMenu' => 'content_production',
            'adminSiteName' => AdminWeb::siteName(),
            'contentGroup' => $contentGroup,
            'platforms' => $this->platformCatalog->all(),
            'wordpressChannels' => DistributionChannel::query()
                ->select(['id', 'name', 'domain'])
                ->where('channel_type', 'wordpress_rest')
                ->where('status', DistributionChannel::STATUS_ACTIVE)
                ->orderBy('name')
                ->get(),
            'wordpressPreflight' => ($wordPressVariant = $contentGroup->variants->firstWhere('platform', 'wordpress'))
                ? $this->wordPressPublicationService->preflight($contentGroup, $wordPressVariant)
                : ['blockers' => ['WordPress 主文章版本不存在。'], 'warnings' => []],
        ]);
    }

    public function generate(GenerateContentVariantsRequest $request, ContentGroup $contentGroup): RedirectResponse
    {
        $platforms = $request->validated('platforms');
        $variants = $contentGroup->variants()
            ->whereIn('platform', $platforms)
            ->get()
            ->keyBy('platform');

        foreach ($platforms as $platform) {
            $variant = $variants->get($platform);
            abort_unless($variant instanceof ContentVariant, 404);
            try {
                $this->variantGenerationService->generate($variant, $request->user('admin'));
            } catch (RuntimeException $exception) {
                return back()->withErrors([$exception->getMessage()]);
            }
        }

        return redirect()
            ->route('admin.content-groups.show', $contentGroup)
            ->with('message', '所选平台版本已生成，等待人工审核。');
    }

    public function regenerate(ContentGroup $contentGroup, ContentVariant $contentVariant): RedirectResponse
    {
        abort_unless($contentVariant->content_group_id === $contentGroup->id, 404);
        $admin = auth('admin')->user();
        abort_unless($admin?->canManageProtectedWorkflows(), 403);

        try {
            $this->variantGenerationService->generate($contentVariant, $admin);
        } catch (RuntimeException $exception) {
            return back()->withErrors([$exception->getMessage()]);
        }

        return redirect()
            ->route('admin.content-groups.show', $contentGroup)
            ->with('message', '平台版本已重新生成，并保留上一版本。');
    }
}
