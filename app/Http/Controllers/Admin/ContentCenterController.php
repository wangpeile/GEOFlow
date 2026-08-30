<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Support\AdminWeb;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class ContentCenterController extends Controller
{
    public function index(): View
    {
        $status = (string) request('status', 'all');
        $query = trim((string) request('q'));
        $articles = Article::query()
            ->with(['contentProductions:id,article_id,name,status,current_stage,created_at', 'contentGroup:id,main_article_id,status'])
            ->when($query !== '', fn (Builder $builder) => $builder->where('title', 'like', '%'.$query.'%'))
            ->when($status === 'review', fn (Builder $builder) => $builder->where('review_status', 'pending'))
            ->when($status === 'draft', fn (Builder $builder) => $builder->where('status', 'draft'))
            ->when($status === 'published', fn (Builder $builder) => $builder->where('status', 'published'))
            ->latest('updated_at')
            ->paginate((int) config('geoflow.admin_items_per_page', 20))
            ->withQueryString();

        return view('admin.content-center.index', [
            'pageTitle' => '内容中心',
            'activeMenu' => 'content_center',
            'adminSiteName' => AdminWeb::siteName(),
            'articles' => $articles,
            'status' => $status,
            'query' => $query,
            'counts' => [
                'all' => Article::query()->count(),
                'review' => Article::query()->where('review_status', 'pending')->count(),
                'draft' => Article::query()->where('status', 'draft')->count(),
                'published' => Article::query()->where('status', 'published')->count(),
            ],
        ]);
    }
}
