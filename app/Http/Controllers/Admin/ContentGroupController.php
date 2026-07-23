<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\ContentGroup;
use App\Services\GeoFlow\ContentGroupService;
use App\Support\AdminWeb;
use App\Support\GeoFlow\ContentPlatformCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ContentGroupController extends Controller
{
    public function __construct(
        private readonly ContentGroupService $contentGroupService,
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
            'activeMenu' => 'articles',
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
                'id', 'content_group_id', 'source_article_id', 'platform', 'title', 'status',
                'review_status', 'version', 'template_version', 'published_url', 'published_at', 'updated_at',
            ]),
        ]);

        return view('admin.content-groups.show', [
            'pageTitle' => __('admin.content_groups.detail_title'),
            'activeMenu' => 'articles',
            'adminSiteName' => AdminWeb::siteName(),
            'contentGroup' => $contentGroup,
            'platforms' => $this->platformCatalog->all(),
        ]);
    }
}
