<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ContentDirectionKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreContentProductionRequest;
use App\Models\ContentProduction;
use App\Models\ContentStageRun;
use App\Models\KnowledgeBase;
use App\Models\UrlImportJob;
use App\Models\WritingRule;
use App\Services\GeoFlow\ContentProductionOrchestrator;
use App\Support\AdminWeb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ContentProductionController extends Controller
{
    public function __construct(private readonly ContentProductionOrchestrator $orchestrator) {}

    public function index(): View
    {
        $this->ensureEnabled();

        $productions = ContentProduction::query()
            ->select([
                'id', 'uuid', 'name', 'topic', 'mode', 'status', 'current_stage', 'language',
                'created_by_admin_id', 'last_error_message', 'created_at', 'updated_at',
            ])
            ->with('createdBy:id,username,display_name')
            ->withCount('stageRuns')
            ->latest()
            ->paginate((int) config('geoflow.admin_items_per_page', 20));

        return view('admin.content-productions.index', $this->viewData([
            'productions' => $productions,
            'creationModes' => $this->creationModes(),
        ]));
    }

    public function create(): View
    {
        $this->ensureEnabled();

        return view('admin.content-productions.create', $this->viewData([
            'writingRules' => WritingRule::query()
                ->select(['id', 'name', 'article_type_id', 'current_version'])
                ->with('articleType:id,name')
                ->where('is_active', true)
                ->orderByDesc('is_preset')
                ->orderBy('name')
                ->get(),
        ]));
    }

    public function store(StoreContentProductionRequest $request): RedirectResponse
    {
        $production = $this->orchestrator->create(
            Auth::guard('admin')->user(),
            $request->validated(),
        );

        return redirect()
            ->route('admin.content-productions.show', $production)
            ->with('message', '内容生产项目已创建。');
    }

    public function show(ContentProduction $contentProduction): View
    {
        $this->ensureEnabled();

        $contentProduction->load([
            'createdBy:id,username,display_name',
            'task:id,name',
            'article:id,title',
            'stageRuns',
            'events.admin:id,username,display_name',
            'evidences.createdBy:id,username,display_name',
            'evidences.knowledgeBase:id,name',
            'directionVersions.createdBy:id,username,display_name',
            'directionVersions.confirmedBy:id,username,display_name',
            'sectionVersions.createdBy:id,username,display_name',
            'articleVersions.createdBy:id,username,display_name',
            'articleVersions.article:id,title,status',
            'qualityReports.createdBy:id,username,display_name',
            'qualityReports.articleVersion:id,content_production_id,version,kind,title',
            'qualityRepairAttempts.createdBy:id,username,display_name',
            'qualityRepairAttempts.repairedArticleVersion:id,content_production_id,version,kind,title',
            'researchReports.createdBy:id,username,display_name',
            'writingRule:id,name',
            'writingRuleVersion:id,writing_rule_id,version,settings_hash',
        ]);
        $latestSections = $contentProduction->sectionVersions
            ->groupBy('section_key')
            ->map->first()
            ->sortBy('position')
            ->values();
        $wizardSteps = $this->wizardSteps($contentProduction, $latestSections->isNotEmpty());
        $resumeStep = collect($wizardSteps)->firstWhere('state', 'current') ?? collect($wizardSteps)->last();

        return view('admin.content-productions.show', $this->viewData([
            'production' => $contentProduction,
            'latestSections' => $latestSections,
            'currentArticleVersion' => $contentProduction->articleVersions->first(),
            'currentQualityReport' => $contentProduction->qualityReports->first(),
            'wizardSteps' => $wizardSteps,
            'resumeStep' => $resumeStep,
            'knowledgeBases' => KnowledgeBase::query()->select('id', 'name')->latest()->get(),
            'urlImportJobs' => UrlImportJob::query()
                ->select('id', 'page_title', 'normalized_url', 'finished_at')
                ->where('status', 'completed')
                ->whereNotNull('finished_at')
                ->latest('finished_at')
                ->limit(50)
                ->get(),
        ]));
    }

    public function retry(ContentProduction $contentProduction, ContentStageRun $stageRun): RedirectResponse
    {
        $this->ensureEnabled();
        abort_unless($stageRun->content_production_id === $contentProduction->id, 404);

        $this->orchestrator->retry(Auth::guard('admin')->user(), $contentProduction, $stageRun);

        return back()->with('message', '失败阶段已加入重试队列。');
    }

    private function ensureEnabled(): void
    {
        abort_unless((bool) config('geoflow.content_production_pipeline_enabled', false), 404);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function creationModes(): array
    {
        return [
            [
                'name' => '可视化 AI 创作',
                'description' => '按步骤完成资料、标题、大纲、分段写作和质量检查。',
                'status' => 'available',
                'route' => route('admin.content-productions.create'),
                'icon' => 'wand-sparkles',
            ],
            [
                'name' => '批量自动创作',
                'description' => '复用写作规则，按关键词或选题批量排队生成。',
                'status' => 'available',
                'route' => route('admin.content-automations.index'),
                'icon' => 'layers-3',
            ],
            [
                'name' => '根据 SERP 创作',
                'description' => '分析搜索结果、竞争内容和内容缺口后再进入创作。',
                'status' => 'available',
                'route' => route('admin.content-productions.create', ['mode' => 'serp']),
                'icon' => 'search-check',
            ],
            [
                'name' => '模板 AI 创作',
                'description' => '从编辑器自由写作，并逐步使用标题、大纲和段落助手。',
                'status' => 'basic',
                'route' => route('admin.articles.create'),
                'icon' => 'layout-template',
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function wizardSteps(ContentProduction $production, bool $hasSections): array
    {
        $directions = $production->directionVersions
            ->whereNull('invalidated_at')
            ->groupBy(fn ($version) => $version->kind->value)
            ->map->first();

        $definitions = [
            ['key' => 'topic', 'label' => '主题与平台', 'anchor' => 'project-context', 'done' => true],
            ['key' => 'research', 'label' => '文章类型与资料', 'anchor' => 'research-evidence', 'done' => $production->evidences->contains(fn ($evidence) => $evidence->usage->value !== 'disabled') && $directions->has(ContentDirectionKind::Brief->value)],
            ['key' => 'keywords', 'label' => '主次关键词', 'anchor' => 'content-direction', 'done' => $directions->has(ContentDirectionKind::Brief->value)],
            ['key' => 'title', 'label' => '标题', 'anchor' => 'content-direction', 'done' => $directions->has(ContentDirectionKind::Titles->value)],
            ['key' => 'structure', 'label' => '结构与篇幅', 'anchor' => 'content-direction', 'done' => $directions->has(ContentDirectionKind::Brief->value)],
            ['key' => 'outline', 'label' => '大纲', 'anchor' => 'content-direction', 'done' => $directions->has(ContentDirectionKind::Outlines->value)],
            ['key' => 'extras', 'label' => '额外功能', 'anchor' => 'article-drafting', 'done' => $hasSections],
            ['key' => 'generate', 'label' => '生成与质量检查', 'anchor' => 'quality-gate', 'done' => $production->qualityReports->isNotEmpty()],
        ];
        $currentFound = false;

        return collect($definitions)->map(function (array $step, int $index) use (&$currentFound): array {
            if ($step['done']) {
                $state = 'done';
            } elseif (! $currentFound) {
                $state = 'current';
                $currentFound = true;
            } else {
                $state = 'pending';
            }

            return [...$step, 'number' => $index + 1, 'state' => $state];
        })->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function viewData(array $data = []): array
    {
        return array_merge([
            'pageTitle' => '内容生产项目',
            'activeMenu' => 'articles',
            'adminSiteName' => AdminWeb::siteName(),
        ], $data);
    }
}
