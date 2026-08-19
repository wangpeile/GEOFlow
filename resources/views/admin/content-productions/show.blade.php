@extends('admin.layouts.app')

@section('content')
    <div class="flex flex-col gap-6 px-4 sm:px-0">
        <div>
            <a href="{{ route('admin.content-productions.index') }}" class="text-sm font-semibold text-blue-600 hover:text-blue-800">← 返回生产项目</a>
            <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">{{ $production->name }}</h1>
                    <p class="mt-1 max-w-4xl text-sm leading-6 text-gray-600">{{ $production->topic }}</p>
                </div>
                <span class="inline-flex w-fit rounded-full bg-slate-100 px-3 py-1 text-sm font-semibold text-slate-700">{{ $production->status->value }}</span>
            </div>
        </div>

        <dl id="project-context" class="scroll-mt-6 grid grid-cols-1 gap-4 rounded-lg border border-gray-200 bg-white p-5 shadow-sm sm:grid-cols-2 lg:grid-cols-4">
            <div><dt class="text-xs font-semibold uppercase text-gray-500">模式</dt><dd class="mt-1 text-sm text-gray-900">{{ $production->mode->value }}</dd></div>
            <div><dt class="text-xs font-semibold uppercase text-gray-500">语言</dt><dd class="mt-1 text-sm text-gray-900">{{ $production->language }}</dd></div>
            <div><dt class="text-xs font-semibold uppercase text-gray-500">创建人</dt><dd class="mt-1 text-sm text-gray-900">{{ $production->createdBy?->name ?: '系统' }}</dd></div>
            <div><dt class="text-xs font-semibold uppercase text-gray-500">项目 UUID</dt><dd class="mt-1 break-all font-mono text-xs text-gray-700">{{ $production->uuid }}</dd></div>
        </dl>

        @include('admin.content-productions.partials.wizard-progress')

        @if ($production->writing_rule_snapshot)
            @php($ruleSettings = (array) data_get($production->writing_rule_snapshot, 'settings', []))
            <section class="rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">
                <div class="font-semibold">写作规则：{{ data_get($production->writing_rule_snapshot, 'name') }} · v{{ data_get($production->writing_rule_snapshot, 'version') }}</div>
                <div class="mt-1 text-xs text-blue-700">已保存不可变快照 · {{ data_get($production->writing_rule_snapshot, 'settings_hash') }}</div>
                <dl class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div><dt class="text-xs font-semibold text-blue-700">首发渠道</dt><dd class="mt-1 font-semibold">WordPress 产品官网</dd></div>
                    <div><dt class="text-xs font-semibold text-blue-700">发布者身份</dt><dd class="mt-1 font-semibold">{{ ($ruleSettings['publisher_identity'] ?? 'official_brand') === 'official_brand' ? '厂商/品牌官方网站' : '独立第三方编辑' }}</dd></div>
                    <div><dt class="text-xs font-semibold text-blue-700">品牌/厂商</dt><dd class="mt-1 font-semibold">{{ $ruleSettings['brand_name'] ?? '未配置' }}</dd></div>
                    <div><dt class="text-xs font-semibold text-blue-700">叙述人称</dt><dd class="mt-1 font-semibold">{{ $ruleSettings['perspective'] ?? '第一人称复数（默认）' }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-xs font-semibold text-blue-700">官网内链</dt><dd class="mt-1">{{ !empty($ruleSettings['include_internal_links']) ? count((array) ($ruleSettings['internal_links'] ?? [])).' 条可用链接' : '不添加' }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-xs font-semibold text-blue-700">官网地址</dt><dd class="mt-1 break-all">{{ $ruleSettings['official_site_url'] ?? '未配置' }}</dd></div>
                </dl>
                @if (!empty($ruleSettings['brand_profile']))<div class="mt-3 rounded-md bg-white/70 p-3 text-xs leading-5"><span class="font-semibold">品牌资料：</span>{{ $ruleSettings['brand_profile'] }}</div>@endif
            </section>
        @endif

        <div id="content-direction" class="scroll-mt-6">
            @include('admin.content-productions.partials.direction')
        </div>
        <div id="article-drafting" class="scroll-mt-6">
            @include('admin.content-productions.partials.article-drafting')
        </div>
        <div id="quality-gate" class="scroll-mt-6">
            @include('admin.content-productions.partials.quality-gate')
        </div>

        <section id="research-evidence" class="scroll-mt-6 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-200 px-6 py-4">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="font-semibold text-gray-900">研究与证据中心</h2>
                        <p class="mt-1 text-sm text-gray-500">正文生成前先保存证据快照；停用的证据不会进入后续写作上下文。</p>
                    </div>
                    <span class="inline-flex w-fit rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700">{{ $production->evidences->count() }} 条证据</span>
                </div>
            </div>

            @if (auth('admin')->user()?->canManageProtectedWorkflows() && config('geoflow.content_production_pipeline_enabled', false))
                <div class="border-b border-gray-200 bg-fuchsia-50 p-6">
                    <form method="POST" action="{{ route('admin.content-productions.research.store', $production) }}" class="space-y-3 rounded-lg border border-fuchsia-200 bg-white p-4">
                        @csrf
                        <div><h3 class="text-sm font-semibold text-gray-900">SERP / 竞品研究</h3><p class="mt-1 text-xs text-gray-500">选择已经完成的 URL 智能采集结果进行对比；不会在这里直接抓取网页。没有来源时自动降级为知识库 / URL 模式。</p></div>
                        <input name="keyword" value="{{ $production->topic }}" required maxlength="500" class="w-full rounded-md border-gray-300 text-sm" aria-label="研究关键词">
                        <select name="url_import_job_ids[]" multiple size="4" class="w-full rounded-md border-gray-300 text-sm">
                            @foreach ($urlImportJobs as $job)
                                <option value="{{ $job->id }}">{{ $job->page_title ?: $job->normalized_url }}</option>
                            @endforeach
                        </select>
                        <button class="rounded-md bg-fuchsia-600 px-4 py-2 text-sm font-semibold text-white hover:bg-fuchsia-700">生成竞品与内容缺口报告</button>
                    </form>
                </div>
                <div class="grid grid-cols-1 gap-5 border-b border-gray-200 bg-slate-50 p-6 lg:grid-cols-3">
                    <form method="POST" action="{{ route('admin.content-productions.evidence.retrieve', $production) }}" class="flex flex-col gap-3 rounded-lg border border-gray-200 bg-white p-4">
                        @csrf
                        <h3 class="text-sm font-semibold text-gray-900">从知识库检索</h3>
                        <select name="knowledge_base_id" required class="rounded-md border-gray-300 text-sm">
                            <option value="">选择知识库</option>
                            @foreach ($knowledgeBases as $knowledgeBase)
                                <option value="{{ $knowledgeBase->id }}">{{ $knowledgeBase->name }}</option>
                            @endforeach
                        </select>
                        <input name="query" value="{{ $production->topic }}" required maxlength="500" class="rounded-md border-gray-300 text-sm" aria-label="检索问题">
                        <button class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">检索并保存</button>
                    </form>

                    <form method="POST" action="{{ route('admin.content-productions.evidence.url-import', $production) }}" class="flex flex-col gap-3 rounded-lg border border-gray-200 bg-white p-4">
                        @csrf
                        <h3 class="text-sm font-semibold text-gray-900">加入 URL 采集资料</h3>
                        <select name="url_import_job_id" required class="rounded-md border-gray-300 text-sm">
                            <option value="">选择已完成任务</option>
                            @foreach ($urlImportJobs as $job)
                                <option value="{{ $job->id }}">{{ $job->page_title ?: $job->normalized_url }}</option>
                            @endforeach
                        </select>
                        <p class="grow text-xs leading-5 text-gray-500">使用采集任务中已经保存的页面正文，不会在此处重新联网。</p>
                        <button class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">加入证据</button>
                    </form>

                    <form method="POST" action="{{ route('admin.content-productions.evidence.store', $production) }}" class="flex flex-col gap-3 rounded-lg border border-gray-200 bg-white p-4">
                        @csrf
                        <h3 class="text-sm font-semibold text-gray-900">手工添加</h3>
                        <input name="source_title" required maxlength="255" placeholder="来源标题" class="rounded-md border-gray-300 text-sm">
                        <input name="source_url" type="url" maxlength="2048" placeholder="来源链接（选填）" class="rounded-md border-gray-300 text-sm">
                        <textarea name="content_snapshot" required maxlength="100000" rows="3" placeholder="证据正文" class="rounded-md border-gray-300 text-sm"></textarea>
                        <input type="hidden" name="usage" value="reference_only">
                        <button class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">保存证据</button>
                    </form>
                </div>
            @endif

            @if ($production->researchReports->isNotEmpty())
                <div class="divide-y divide-fuchsia-100 border-b border-gray-200 bg-fuchsia-50/40">
                    @foreach ($production->researchReports as $report)
                        <article class="px-6 py-5">
                            <div class="flex flex-wrap items-center gap-2"><h3 class="font-semibold text-gray-900">{{ $report->keyword }}</h3><span class="rounded-full bg-white px-2 py-0.5 text-xs font-semibold text-fuchsia-700 ring-1 ring-fuchsia-200">{{ $report->status }}</span></div>
                            <p class="mt-2 text-sm text-gray-700">{{ data_get($report->analysis, 'summary') }}</p>
                            <div class="mt-3 grid gap-3 md:grid-cols-2">
                                <div><div class="text-xs font-semibold text-gray-500">内容缺口</div><ul class="mt-1 list-disc space-y-1 pl-5 text-sm text-gray-700">@foreach ((array) data_get($report->analysis, 'content_gaps', []) as $gap)<li>{{ $gap }}</li>@endforeach</ul></div>
                                <div><div class="text-xs font-semibold text-gray-500">建议</div><ul class="mt-1 list-disc space-y-1 pl-5 text-sm text-gray-700">@foreach ((array) data_get($report->analysis, 'recommendations', []) as $recommendation)<li>{{ $recommendation }}</li>@endforeach</ul></div>
                            </div>
                            <p class="mt-3 text-xs text-gray-400">来源 {{ count((array) $report->sources) }} 个 · 采集 {{ $report->collected_at?->format('Y-m-d H:i:s') }} · 报告 #{{ $report->id }}</p>
                        </article>
                    @endforeach
                </div>
            @endif

            <div class="divide-y divide-gray-100">
                @forelse ($production->evidences as $evidence)
                    <article class="flex flex-col gap-4 px-6 py-5">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="font-semibold text-gray-900">{{ $evidence->source_title ?: '未命名证据' }}</h3>
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">{{ $evidence->source_type->value }}</span>
                                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $evidence->usage->value === 'must_cite' ? 'bg-emerald-50 text-emerald-700' : ($evidence->usage->value === 'disabled' ? 'bg-red-50 text-red-700' : 'bg-blue-50 text-blue-700') }}">{{ $evidence->usage->value }}</span>
                                </div>
                                @if ($evidence->source_url)
                                    <a href="{{ $evidence->source_url }}" target="_blank" rel="noopener noreferrer" class="mt-1 block break-all text-xs text-blue-600 hover:text-blue-800">{{ $evidence->source_url }}</a>
                                @endif
                                <p class="mt-2 text-sm leading-6 text-gray-600">{{ $evidence->excerpt }}</p>
                                <p class="mt-2 text-xs text-gray-400">采集：{{ $evidence->collected_at?->format('Y-m-d H:i:s') ?: '—' }}@if ($evidence->confidence !== null) · 置信度 {{ number_format($evidence->confidence, 3) }}@endif</p>
                            </div>
                            @if (auth('admin')->user()?->canManageProtectedWorkflows() && config('geoflow.content_production_pipeline_enabled', false))
                                <div class="flex shrink-0 flex-wrap items-center gap-2">
                                    <form method="POST" action="{{ route('admin.content-productions.evidence.update', [$production, $evidence]) }}">
                                        @csrf
                                        @method('PATCH')
                                        <select name="usage" onchange="this.form.submit()" class="rounded-md border-gray-300 py-1 text-xs">
                                            <option value="reference_only" @selected($evidence->usage->value === 'reference_only')>仅参考</option>
                                            <option value="must_cite" @selected($evidence->usage->value === 'must_cite')>必须引用</option>
                                            <option value="disabled" @selected($evidence->usage->value === 'disabled')>停用</option>
                                        </select>
                                    </form>
                                    <form method="POST" action="{{ route('admin.content-productions.evidence.destroy', [$production, $evidence]) }}" onsubmit="return confirm('确认删除这条证据？')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="text-xs font-semibold text-red-600 hover:text-red-800">删除</button>
                                    </form>
                                </div>
                            @endif
                        </div>
                    </article>
                @empty
                    <div class="px-6 py-10 text-center">
                        <p class="text-sm font-semibold text-amber-700">当前没有可用证据，系统不会生成虚构引用。</p>
                        <p class="mt-1 text-sm text-gray-500">请先从知识库检索、加入 URL 采集资料，或手工补充依据。</p>
                    </div>
                @endforelse
            </div>
        </section>

        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-200 px-6 py-4">
                <h2 class="font-semibold text-gray-900">阶段时间线</h2>
                <p class="mt-1 text-sm text-gray-500">失败阶段可以创建新的重试记录，历史尝试不会被覆盖。</p>
            </div>
            <div class="divide-y divide-gray-100">
                @foreach ($production->stageRuns as $run)
                    <div class="flex flex-col gap-3 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex min-w-0 items-start gap-3">
                            <span class="inline-flex h-7 min-w-7 items-center justify-center rounded-full bg-blue-50 px-2 text-xs font-bold text-blue-700">{{ $run->sequence }}</span>
                            <div class="min-w-0">
                                <div class="font-semibold text-gray-900">{{ $run->stage->value }} <span class="text-xs font-normal text-gray-400">尝试 {{ $run->attempt }}</span></div>
                                @if ($run->error_message)
                                    <p class="mt-1 text-sm text-red-600">{{ $run->error_message }}</p>
                                @endif
                                <p class="mt-1 text-xs text-gray-400">{{ $run->started_at?->format('Y-m-d H:i:s') ?: '尚未开始' }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ $run->status->value }}</span>
                            @if ($run->status === \App\Enums\ContentStageStatus::Failed && auth('admin')->user()?->isSuperAdmin() && config('geoflow.content_production_pipeline_enabled', false))
                                <form method="POST" action="{{ route('admin.content-productions.stages.retry', [$production, $run]) }}">
                                    @csrf
                                    <button class="text-sm font-semibold text-blue-600 hover:text-blue-800">重试</button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-200 px-6 py-4">
                <h2 class="font-semibold text-gray-900">审计事件</h2>
            </div>
            <div class="divide-y divide-gray-100">
                @forelse ($production->events as $event)
                    <div class="flex flex-col gap-1 px-6 py-4 text-sm sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <span class="font-semibold text-gray-900">{{ $event->event }}</span>
                            <span class="ml-2 text-gray-500">{{ $event->from_status ?: '—' }} → {{ $event->to_status ?: '—' }}</span>
                        </div>
                        <div class="text-xs text-gray-400">{{ $event->admin?->name ?: '系统' }} · {{ $event->created_at?->format('Y-m-d H:i:s') }}</div>
                    </div>
                @empty
                    <div class="px-6 py-8 text-center text-sm text-gray-500">暂无审计事件。</div>
                @endforelse
            </div>
        </section>
    </div>
@endsection
