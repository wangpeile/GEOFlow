<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreContentTopicIdeaRequest;
use App\Http\Requests\Admin\StoreContentTopicRequest;
use App\Models\Category;
use App\Models\ContentTopic;
use App\Models\ContentTopicIdea;
use App\Models\KnowledgeBase;
use App\Models\WritingRule;
use App\Support\AdminWeb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ContentTopicController extends Controller
{
    public function index(): View
    {
        return view('admin.content-topics.index', $this->viewData([
            'topics' => ContentTopic::query()->withCount(['productions', 'ideas'])->latest()->paginate((int) config('geoflow.admin_items_per_page', 20)),
        ]));
    }

    public function create(): View
    {
        return view('admin.content-topics.form', $this->formData());
    }

    public function store(StoreContentTopicRequest $request): RedirectResponse
    {
        $topic = ContentTopic::query()->create(array_merge($request->validated(), ['created_by_admin_id' => Auth::guard('admin')->id()]));

        return redirect()->route('admin.content-topics.show', $topic)->with('message', '内容专题已创建。');
    }

    public function show(ContentTopic $contentTopic): View
    {
        $contentTopic->load(['category:id,name', 'writingRule:id,name,current_version']);
        $plans = $contentTopic->productionPlans()
            ->withCount([
                'automationRuns as successful_runs_count' => fn ($query) => $query->where('status', 'completed'),
                'automationRuns as failed_runs_count' => fn ($query) => $query->where('status', 'failed'),
            ])
            ->latest()
            ->get(['id', 'name', 'schedule_enabled', 'daily_production_limit', 'next_run_at', 'last_error_message']);

        return view('admin.content-topics.show', $this->viewData([
            'topic' => $contentTopic,
            'ideas' => $contentTopic->ideas()->paginate(20, ['*'], 'ideas_page'),
            'productions' => $contentTopic->productions()->with('createdBy:id,username,display_name')->latest()->paginate(10, ['*'], 'productions_page'),
            'ideaStatuses' => ContentTopicIdea::STATUSES,
            'plans' => $plans,
        ]));
    }

    public function edit(ContentTopic $contentTopic): View
    {
        return view('admin.content-topics.form', $this->formData($contentTopic));
    }

    public function update(StoreContentTopicRequest $request, ContentTopic $contentTopic): RedirectResponse
    {
        $contentTopic->update($request->validated());

        return redirect()->route('admin.content-topics.show', $contentTopic)->with('message', '内容专题已保存；已创建文章仍保留创建时的专题快照。');
    }

    public function storeIdea(StoreContentTopicIdeaRequest $request, ContentTopic $contentTopic): RedirectResponse
    {
        $contentTopic->ideas()->create($request->validated());

        return back()->with('message', '选题已加入选题池。');
    }

    public function updateIdea(StoreContentTopicIdeaRequest $request, ContentTopic $contentTopic, ContentTopicIdea $contentTopicIdea): RedirectResponse
    {
        abort_unless($contentTopicIdea->content_topic_id === $contentTopic->id, 404);
        $contentTopicIdea->update($request->validated());

        return back()->with('message', '选题已更新。');
    }

    private function formData(?ContentTopic $topic = null): array
    {
        return $this->viewData([
            'topic' => $topic,
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'writingRules' => WritingRule::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'current_version']),
            'knowledgeBases' => KnowledgeBase::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    private function viewData(array $data): array
    {
        return array_merge(['pageTitle' => '内容专题', 'activeMenu' => 'content_production', 'adminSiteName' => AdminWeb::siteName()], $data);
    }
}
