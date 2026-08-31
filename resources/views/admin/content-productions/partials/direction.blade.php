@php
    $usableDirections = $production->directionVersions->whereNull('invalidated_at');
    $briefVersion = $usableDirections->filter(fn ($item) => $item->kind->value === 'brief')->sortByDesc('version')->first();
    $titleVersion = $usableDirections->filter(fn ($item) => $item->kind->value === 'titles')->sortByDesc('version')->first();
    $outlineVersion = $usableDirections->filter(fn ($item) => $item->kind->value === 'outlines')->sortByDesc('version')->first();
    $brief = $briefVersion?->payload ?? [];
    $titles = $titleVersion?->payload ?? [];
    $outlines = $outlineVersion?->payload ?? [];
@endphp

<section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h2 class="font-semibold text-gray-900">内容方向向导</h2>
            <p class="mt-1 text-sm text-gray-500">依次确认简报、标题和大纲。每次修改都会保留新版本，不覆盖历史结果。</p>
        </div>
        <span class="w-fit rounded-full bg-violet-50 px-3 py-1 text-xs font-semibold text-violet-700">写作前确认</span>
    </div>

    @if (auth('admin')->user()?->canManageProtectedWorkflows() && config('geoflow.content_production_pipeline_enabled', false))
        <div class="mt-5 grid gap-5 xl:grid-cols-3">
            <div class="rounded-lg border border-gray-200 p-4">
                <div class="flex items-center justify-between gap-2">
                    <h3 class="text-sm font-semibold text-gray-900">1. 内容简报</h3>
                    @if ($briefVersion)<span class="text-xs text-gray-500">v{{ $briefVersion->version }}{{ $briefVersion->confirmed_at ? ' · 已确认' : '' }}</span>@endif
                </div>
                <form method="POST" action="{{ route('admin.content-productions.direction.brief.generate', $production) }}" class="mt-3">
                    @csrf
                    <button class="w-full rounded-md border border-violet-300 px-3 py-2 text-sm font-semibold text-violet-700">根据证据生成草稿</button>
                </form>
                <form method="POST" action="{{ route('admin.content-productions.direction.brief.save', $production) }}" class="mt-3 space-y-3">
                    @csrf
                    <label class="block text-xs font-semibold text-gray-700">文章类型<input name="article_type" value="{{ old('article_type', $brief['article_type'] ?? '深度博客文章') }}" required class="mt-1 w-full rounded-md border-gray-300 text-sm"></label>
                    <label class="block text-xs font-semibold text-gray-700">目标读者<textarea name="target_audience" rows="2" required class="mt-1 w-full rounded-md border-gray-300 text-sm">{{ old('target_audience', $brief['target_audience'] ?? '') }}</textarea></label>
                    <label class="block text-xs font-semibold text-gray-700">搜索意图<textarea name="search_intent" rows="2" required class="mt-1 w-full rounded-md border-gray-300 text-sm">{{ old('search_intent', $brief['search_intent'] ?? '') }}</textarea></label>
                    <label class="block text-xs font-semibold text-gray-700">内容角度<textarea name="content_angle" rows="3" required class="mt-1 w-full rounded-md border-gray-300 text-sm">{{ old('content_angle', $brief['content_angle'] ?? '') }}</textarea></label>
                    <label class="block text-xs font-semibold text-gray-700">必须覆盖（每行一项）<textarea name="must_cover" rows="3" class="mt-1 w-full rounded-md border-gray-300 text-sm">{{ old('must_cover', implode("\n", $brief['must_cover'] ?? [])) }}</textarea></label>
                    <label class="block text-xs font-semibold text-gray-700">避免内容（每行一项）<textarea name="avoid_topics" rows="3" class="mt-1 w-full rounded-md border-gray-300 text-sm">{{ old('avoid_topics', implode("\n", $brief['avoid_topics'] ?? [])) }}</textarea></label>
                    <div class="max-h-32 space-y-2 overflow-y-auto rounded-md border border-gray-200 p-2">
                        @forelse ($production->evidences->filter(fn ($item) => $item->usage->value !== 'disabled') as $evidence)
                            <label class="flex gap-2 text-xs text-gray-700">
                                <input type="checkbox" name="evidence_ids[]" value="{{ $evidence->id }}" @checked(in_array($evidence->id, $brief['evidence_ids'] ?? [], true)) class="mt-0.5 rounded border-gray-300">
                                <span>{{ $evidence->source_title ?: '未命名证据' }}</span>
                            </label>
                        @empty
                            <p class="text-xs text-amber-700">尚无可用证据。</p>
                        @endforelse
                    </div>
                    <button class="w-full rounded-md bg-violet-600 px-3 py-2 text-sm font-semibold text-white">保存新版本</button>
                </form>
                @if ($briefVersion && !$briefVersion->confirmed_at)
                    <form method="POST" action="{{ route('admin.content-productions.direction.confirm', [$production, 'brief']) }}" class="mt-2">@csrf<button class="w-full rounded-md border border-emerald-300 px-3 py-2 text-sm font-semibold text-emerald-700">确认简报</button></form>
                @endif
            </div>

            <div class="rounded-lg border border-gray-200 p-4">
                <div class="flex items-center justify-between gap-2">
                    <h3 class="text-sm font-semibold text-gray-900">2. 标题候选</h3>
                    @if ($titleVersion)<span class="text-xs text-gray-500">v{{ $titleVersion->version }}{{ ($titles['generation_source'] ?? '') === 'laravel_ai_sdk' ? ' · AI 生成' : ' · 规则生成' }}{{ $titleVersion->confirmed_at ? ' · 已确认' : '' }}</span>@endif
                </div>
                <form method="POST" action="{{ route('admin.content-productions.direction.titles.generate', $production) }}" class="mt-3">@csrf<button class="w-full rounded-md border border-violet-300 px-3 py-2 text-sm font-semibold text-violet-700">生成 5 个标题</button></form>
                @if ($titleVersion)
                    @if (!empty($titles['generation_note']))<p class="mt-2 text-xs text-gray-500">{{ $titles['generation_note'] }}</p>@endif
                    <form method="POST" action="{{ route('admin.content-productions.direction.titles.select', $production) }}" class="mt-3 space-y-2">
                        @csrf
                        @foreach ($titles['candidates'] ?? [] as $candidate)
                            <label class="flex gap-2 rounded-md border border-gray-200 p-3 text-sm">
                                <input type="radio" name="candidate_id" value="{{ $candidate['id'] }}" @checked(($titles['selected_id'] ?? null) === $candidate['id']) class="mt-1 border-gray-300">
                                <span><strong class="block text-gray-900">{{ $candidate['title'] }}</strong><small class="text-gray-500">{{ $candidate['rationale'] }}</small></span>
                            </label>
                        @endforeach
                        <label class="block text-xs font-semibold text-gray-700">自定义标题<input name="custom_title" placeholder="{{ $titles['selected_title'] ?? '输入自己的标题' }}" class="mt-1 w-full rounded-md border-gray-300 text-sm"></label>
                        <button class="w-full rounded-md bg-violet-600 px-3 py-2 text-sm font-semibold text-white">保存标题</button>
                    </form>
                    @if (!empty($titles['selected_title']) && !$titleVersion->confirmed_at)
                        <form method="POST" action="{{ route('admin.content-productions.direction.confirm', [$production, 'titles']) }}" class="mt-2">@csrf<button class="w-full rounded-md border border-emerald-300 px-3 py-2 text-sm font-semibold text-emerald-700">确认标题</button></form>
                    @endif
                @else
                    <p class="mt-4 text-sm text-gray-500">保存简报后再生成标题。</p>
                @endif
            </div>

            <div class="rounded-lg border border-gray-200 p-4">
                <div class="flex items-center justify-between gap-2">
                    <h3 class="text-sm font-semibold text-gray-900">3. 大纲对比</h3>
                    @if ($outlineVersion)<span class="text-xs text-gray-500">v{{ $outlineVersion->version }}{{ ($outlines['generation_source'] ?? '') === 'laravel_ai_sdk' ? ' · AI 生成' : ' · 规则生成' }}{{ $outlineVersion->confirmed_at ? ' · 已确认' : '' }}</span>@endif
                </div>
                <form method="POST" action="{{ route('admin.content-productions.direction.outlines.generate', $production) }}" class="mt-3">@csrf<button class="w-full rounded-md border border-violet-300 px-3 py-2 text-sm font-semibold text-violet-700">生成两个大纲</button></form>
                @if (!empty($outlines['generation_note']))<p class="mt-2 text-xs text-gray-500">{{ $outlines['generation_note'] }}</p>@endif
                @foreach ($outlines['candidates'] ?? [] as $candidate)
                    <div class="mt-3 rounded-md border p-3 {{ ($outlines['selected_id'] ?? null) === $candidate['id'] ? 'border-violet-400 bg-violet-50' : 'border-gray-200' }}">
                        <div class="flex items-center justify-between gap-2">
                            <strong class="text-sm text-gray-900">{{ $candidate['name'] }}</strong>
                            <form method="POST" action="{{ route('admin.content-productions.direction.outlines.update', $production) }}">@csrf<input type="hidden" name="candidate_id" value="{{ $candidate['id'] }}"><button name="action" value="select" class="text-xs font-semibold text-violet-700">选择</button></form>
                        </div>
                        <div class="mt-2 space-y-2">
                            @foreach ($candidate['nodes'] as $node)
                                <form method="POST" action="{{ route('admin.content-productions.direction.outlines.update', $production) }}" class="rounded border border-gray-200 bg-white p-2">
                                    @csrf
                                    <input type="hidden" name="candidate_id" value="{{ $candidate['id'] }}"><input type="hidden" name="node_id" value="{{ $node['id'] }}">
                                    <div class="flex gap-2">
                                        <select name="level" class="w-20 rounded-md border-gray-300 py-1 text-xs"><option value="h2" @selected($node['level'] === 'h2')>H2</option><option value="h3" @selected($node['level'] === 'h3')>H3</option></select>
                                        <input name="heading" value="{{ $node['heading'] }}" class="min-w-0 flex-1 rounded-md border-gray-300 py-1 text-xs">
                                    </div>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        <button name="action" value="edit" class="text-xs text-violet-700">保存</button><button name="action" value="up" class="text-xs text-gray-600">上移</button><button name="action" value="down" class="text-xs text-gray-600">下移</button><button name="action" value="regenerate" class="text-xs text-blue-700">重写</button><button name="action" value="delete" class="text-xs text-red-600">删除</button>
                                    </div>
                                </form>
                            @endforeach
                        </div>
                    </div>
                @endforeach
                @if ($outlineVersion && !empty($outlines['selected_id']) && !$outlineVersion->confirmed_at)
                    <form method="POST" action="{{ route('admin.content-productions.direction.confirm', [$production, 'outlines']) }}" class="mt-3">@csrf<button class="w-full rounded-md border border-emerald-300 px-3 py-2 text-sm font-semibold text-emerald-700">确认大纲</button></form>
                @endif
            </div>
        </div>
    @else
        <p class="mt-4 rounded-md bg-slate-50 px-4 py-3 text-sm text-gray-600">新内容管线当前关闭，仅展示已保存的方向版本。</p>
    @endif

    @if ($production->directionVersions->whereNotNull('invalidated_at')->isNotEmpty())
        <p class="mt-4 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-800">上游方向已调整，旧的下游结果已标记为“需要重新生成”，历史版本仍然保留。</p>
    @endif
</section>
