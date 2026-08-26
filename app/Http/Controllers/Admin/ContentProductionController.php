<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ContentDirectionKind;
use App\Enums\ContentProductionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreContentProductionRequest;
use App\Http\Requests\Admin\UpdateContentProductionOwnershipRequest;
use App\Models\Author;
use App\Models\Category;
use App\Models\ContentProduction;
use App\Models\ContentStageRun;
use App\Models\ContentTopic;
use App\Models\ContentTopicIdea;
use App\Models\KnowledgeBase;
use App\Models\UrlImportJob;
use App\Models\WritingRule;
use App\Services\GeoFlow\ContentProductionOrchestrator;
use App\Support\AdminWeb;
use App\Support\GeoFlow\ContentPlatformCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ContentProductionController extends Controller
{
    public function __construct(
        private readonly ContentProductionOrchestrator $orchestrator,
        private readonly ContentPlatformCatalog $platformCatalog,
    ) {}

    public function index(): View
    {
        $this->ensureEnabled();

        $statusCounts = ContentProduction::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

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
            'workQueues' => [
                [
                    'label' => '待继续',
                    'description' => '等待补充资料、确认方向或继续写作的文章。',
                    'count' => (int) ($statusCounts[ContentProductionStatus::Draft->value] ?? 0)
                        + (int) ($statusCounts[ContentProductionStatus::WaitingInput->value] ?? 0),
                    'icon' => 'pencil-line',
                    'tone' => 'blue',
                ],
                [
                    'label' => '生成中',
                    'description' => '已经进入队列或正在执行生产阶段。',
                    'count' => (int) ($statusCounts[ContentProductionStatus::Queued->value] ?? 0)
                        + (int) ($statusCounts[ContentProductionStatus::Running->value] ?? 0),
                    'icon' => 'loader-circle',
                    'tone' => 'amber',
                ],
                [
                    'label' => '待审核',
                    'description' => '文章草稿或质量检查已经完成，等待人工确认。',
                    'count' => (int) ($statusCounts[ContentProductionStatus::WaitingReview->value] ?? 0),
                    'icon' => 'clipboard-check',
                    'tone' => 'violet',
                ],
                [
                    'label' => '已完成',
                    'description' => '主文章已完成，可进入发布包生成与分发。',
                    'count' => (int) ($statusCounts[ContentProductionStatus::Completed->value] ?? 0),
                    'icon' => 'circle-check-big',
                    'tone' => 'emerald',
                ],
            ],
        ]));
    }

    public function create(Request $request): View
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
            'categories' => Category::query()->select(['id', 'name'])->orderBy('name')->get(),
            'authors' => Author::query()->select(['id', 'name'])->orderBy('name')->get(),
            'contentTopics' => ContentTopic::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'category_id', 'writing_rule_id']),
            'selectedTopic' => $request->integer('content_topic_id') ? ContentTopic::query()->find($request->integer('content_topic_id')) : null,
            'selectedIdea' => $request->integer('content_topic_idea_id') ? ContentTopicIdea::query()->find($request->integer('content_topic_idea_id')) : null,
            'platformRequirements' => collect([
                'wordpress' => 'wordpress',
                'baijiahao' => 'baijiahao',
                'qq' => 'qq_news',
                'netease' => 'netease',
                'sohu' => 'sohu',
                'toutiao' => 'toutiao',
                'zhihu' => 'zhihu',
                'wechat' => 'wechat_official',
            ])->map(fn (string $platform): array => $this->platformCatalog->get($platform))->all(),
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
            ->with('message', '文章工作单已创建。');
    }

    public function show(Request $request, ContentProduction $contentProduction): View
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
        $requestedWorkbenchStep = $request->query('stage');
        $activeWorkbenchStep = $this->activeWorkbenchStep(
            is_string($requestedWorkbenchStep) ? $requestedWorkbenchStep : null,
            $wizardSteps,
            (string) data_get($resumeStep, 'key'),
        );

        return view('admin.content-productions.show', $this->viewData([
            'production' => $contentProduction,
            'latestSections' => $latestSections,
            'currentArticleVersion' => $contentProduction->articleVersions->first(),
            'currentQualityReport' => $contentProduction->qualityReports->first(),
            'wizardSteps' => $wizardSteps,
            'resumeStep' => $resumeStep,
            'activeWorkbenchStep' => $activeWorkbenchStep,
            'knowledgeBases' => KnowledgeBase::query()->select('id', 'name')->latest()->get(),
            'categories' => Category::query()->select(['id', 'name'])->orderBy('name')->get(),
            'authors' => Author::query()->select(['id', 'name'])->orderBy('name')->get(),
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

    public function updateOwnership(
        UpdateContentProductionOwnershipRequest $request,
        ContentProduction $contentProduction,
    ): RedirectResponse {
        $validated = $request->validated();

        DB::transaction(function () use ($contentProduction, $validated): void {
            $locked = ContentProduction::query()->lockForUpdate()->findOrFail($contentProduction->id);
            $locked->forceFill([
                'context' => array_replace($locked->context ?? [], [
                    'category_id' => (int) $validated['category_id'],
                    'author_id' => (int) $validated['author_id'],
                ]),
            ])->save();
        });

        return back()->with('message', '文章分类和作者已保存。');
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
                'name' => '新建文章',
                'description' => '为一篇主文章建立工作单，按步骤完成资料、标题、大纲、写作和质量检查。',
                'status' => 'available',
                'route' => route('admin.content-productions.create'),
                'icon' => 'wand-sparkles',
            ],
            [
                'name' => '生产计划',
                'description' => '复用写作规则，按关键词或选题批量排队生成。',
                'status' => 'available',
                'route' => route('admin.content-automations.index'),
                'icon' => 'layers-3',
            ],
            [
                'name' => '从资料创作',
                'description' => '创建工作单后，在资料研究阶段加入知识库、URL 与 SERP 证据。',
                'status' => 'available',
                'route' => route('admin.content-productions.create'),
                'icon' => 'search-check',
            ],
            [
                'name' => '自由编辑',
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
            ['key' => 'context', 'label' => '文章设置', 'description' => '主题、规则、分类与目标平台', 'done' => true],
            ['key' => 'research', 'label' => '资料研究', 'description' => '知识库、URL 与 SERP 证据', 'done' => $production->evidences->contains(fn ($evidence) => $evidence->usage->value !== 'disabled')],
            ['key' => 'direction', 'label' => '创作方向', 'description' => '关键词、标题与大纲', 'done' => $directions->has(ContentDirectionKind::Brief->value) && $directions->has(ContentDirectionKind::Outlines->value)],
            ['key' => 'drafting', 'label' => '撰写文章', 'description' => '建立章节、生成草稿并组装文章', 'done' => $hasSections && $production->articleVersions->isNotEmpty()],
            ['key' => 'quality', 'label' => '质量审核', 'description' => '检查、修复与人工确认', 'done' => $production->qualityReports->isNotEmpty()],
            ['key' => 'delivery', 'label' => '发布与改写', 'description' => '建立发布包并生成平台版本', 'done' => $production->status === ContentProductionStatus::Completed],
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
     * @param  array<int, array<string, mixed>>  $wizardSteps
     */
    private function activeWorkbenchStep(?string $requestedStep, array $wizardSteps, string $fallback): string
    {
        $availableSteps = collect($wizardSteps)->pluck('key')->all();

        if (is_string($requestedStep) && in_array($requestedStep, $availableSteps, true)) {
            return $requestedStep;
        }

        return in_array($fallback, $availableSteps, true) ? $fallback : 'context';
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function viewData(array $data = []): array
    {
        return array_merge([
            'pageTitle' => '内容生产',
            'activeMenu' => 'content_production',
            'adminSiteName' => AdminWeb::siteName(),
        ], $data);
    }
}
