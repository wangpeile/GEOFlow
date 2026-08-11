<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\ContentGroup;
use App\Models\ContentVariant;
use App\Http\Requests\Admin\GenerateContentVariantsRequest;
use App\Services\GeoFlow\ContentGroupService;
use App\Services\GeoFlow\ContentVariantGenerationService;
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
            'mainArticle:id,title,status,review_status,updated_at',
            'task:id,name',
            'variants' => fn ($query) => $query->select([
                'id', 'content_group_id', 'source_article_id', 'platform', 'title', 'excerpt', 'content',
                'tags', 'image_requirements', 'status', 'review_status', 'version', 'template_version',
                'generation_meta', 'source_content_hash', 'fact_check', 'failure_message',
                'published_url', 'published_at', 'updated_at',
            ]),
            'variants.versions' => fn ($query) => $query->select([
                'id', 'content_variant_id', 'version', 'title', 'template_version', 'generation_meta',
                'created_by', 'created_at',
            ])->with('creator:id,username'),
        ]);

        return view('admin.content-groups.show', [
            'pageTitle' => __('admin.content_groups.detail_title'),
            'activeMenu' => 'content_production',
            'adminSiteName' => AdminWeb::siteName(),
            'contentGroup' => $contentGroup,
            'platforms' => $this->platformCatalog->all(),
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
