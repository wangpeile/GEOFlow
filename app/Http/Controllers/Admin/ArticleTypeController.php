<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreArticleTypeRequest;
use App\Models\ArticleType;
use App\Support\AdminWeb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ArticleTypeController extends Controller
{
    public function index(): View
    {
        return view('admin.article-types.index', $this->viewData([
            'articleTypes' => ArticleType::query()->withCount('writingRules')->orderByDesc('is_system')->orderBy('name')->get(),
        ]));
    }

    public function create(): View
    {
        return view('admin.article-types.form', $this->viewData(['articleType' => null]));
    }

    public function store(StoreArticleTypeRequest $request): RedirectResponse
    {
        ArticleType::query()->create($this->payload($request->validated(), true));

        return redirect()->route('admin.article-types.index')->with('message', '文章类型已创建。');
    }

    public function edit(ArticleType $articleType): View
    {
        abort_if($articleType->is_system, 403, '系统文章类型不可编辑。');

        return view('admin.article-types.form', $this->viewData(['articleType' => $articleType]));
    }

    public function update(StoreArticleTypeRequest $request, ArticleType $articleType): RedirectResponse
    {
        abort_if($articleType->is_system, 403, '系统文章类型不可编辑。');

        $articleType->update($this->payload($request->validated()));

        return redirect()->route('admin.article-types.index')->with('message', '文章类型已更新。');
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function payload(array $data, bool $includeCreator = false): array
    {
        $payload = [
            'code' => $data['code'], 'name' => $data['name'], 'description' => $data['description'] ?? null,
            'default_settings' => [
                'min_words' => $data['default_min_words'], 'max_words' => $data['default_max_words'],
                'min_headings' => $data['default_min_headings'], 'max_headings' => $data['default_max_headings'],
                'structure_notes' => $data['structure_notes'] ?? null,
            ],
            'is_active' => $data['is_active'],
        ];

        if ($includeCreator) {
            $payload['created_by_admin_id'] = Auth::guard('admin')->id();
        }

        return $payload;
    }

    /** @param array<string, mixed> $data */
    private function viewData(array $data): array
    {
        return array_merge(['pageTitle' => '文章类型', 'activeMenu' => 'articles', 'adminSiteName' => AdminWeb::siteName()], $data);
    }
}
