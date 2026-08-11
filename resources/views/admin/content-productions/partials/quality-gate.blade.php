<section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
    <div class="border-b border-gray-200 px-6 py-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h2 class="font-semibold text-gray-900">质量门禁与定向修复</h2>
                <p class="mt-1 text-sm leading-6 text-gray-500">阻断项必须处理后才能发布；警告项可以人工确认。自动修复只处理明确可替换的问题，并保留前后版本。</p>
            </div>
            @if ($currentArticleVersion && auth('admin')->user()?->canManageProtectedWorkflows() && config('geoflow.content_production_pipeline_enabled', false))
                <form method="POST" action="{{ route('admin.content-productions.article.quality.inspect', $production) }}">
                    @csrf
                    <button class="whitespace-nowrap rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">执行质量检查</button>
                </form>
            @endif
        </div>
    </div>

    @if ($currentQualityReport)
        @php
            $reportClasses = match ($currentQualityReport->status) {
                \App\Enums\QualityReportStatus::Blocked => 'bg-red-50 text-red-700',
                \App\Enums\QualityReportStatus::Warning => 'bg-amber-50 text-amber-700',
                default => 'bg-emerald-50 text-emerald-700',
            };
            $repairableIssues = collect($currentQualityReport->issues)->where('repairable', true);
            $repairAttempts = $production->qualityRepairAttempts->count();
        @endphp
        <div class="grid grid-cols-2 gap-4 border-b border-gray-200 bg-slate-50 px-6 py-4 sm:grid-cols-4">
            <div><p class="text-xs font-semibold text-gray-500">检查结果</p><span class="mt-1 inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $reportClasses }}">{{ $currentQualityReport->status->value }}</span></div>
            <div><p class="text-xs font-semibold text-gray-500">阻断项</p><p class="mt-1 text-lg font-bold text-red-700">{{ $currentQualityReport->summary['blockers'] ?? 0 }}</p></div>
            <div><p class="text-xs font-semibold text-gray-500">警告项</p><p class="mt-1 text-lg font-bold text-amber-700">{{ $currentQualityReport->summary['warnings'] ?? 0 }}</p></div>
            <div><p class="text-xs font-semibold text-gray-500">报告版本</p><p class="mt-1 text-sm font-semibold text-gray-900">#{{ $currentQualityReport->version }} · 文章 #{{ $currentQualityReport->articleVersion?->version }}</p></div>
        </div>

        <form method="POST" action="{{ route('admin.content-productions.article.quality.repair', [$production, $currentQualityReport]) }}">
            @csrf
            <div class="divide-y divide-gray-100">
                @forelse ($currentQualityReport->issues as $issue)
                    <label class="flex items-start gap-3 px-6 py-4">
                        @if (($issue['repairable'] ?? false) && $repairAttempts < \App\Services\GeoFlow\TargetedRepairService::MAX_ATTEMPTS && auth('admin')->user()?->canManageProtectedWorkflows())
                            <input type="checkbox" name="issue_ids[]" value="{{ $issue['id'] }}" class="mt-1 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        @else
                            <span class="mt-1 h-4 w-4 shrink-0 rounded-full border border-gray-300 bg-gray-50"></span>
                        @endif
                        <span class="min-w-0 grow">
                            <span class="flex flex-wrap items-center gap-2">
                                <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ ($issue['severity'] ?? '') === 'blocker' ? 'bg-red-50 text-red-700' : 'bg-amber-50 text-amber-700' }}">{{ $issue['severity'] ?? 'warning' }}</span>
                                <span class="text-xs font-semibold uppercase text-gray-500">{{ $issue['field'] ?? 'body' }}</span>
                                @if ($issue['repairable'] ?? false)<span class="text-xs font-semibold text-blue-600">可定向修复</span>@endif
                            </span>
                            <span class="mt-1 block text-sm font-semibold text-gray-900">{{ $issue['message'] }}</span>
                            <span class="mt-1 block break-words text-xs text-gray-500">位置：{{ $issue['location'] }}</span>
                            @if ($issue['suggestion'] ?? null)<span class="mt-1 block text-xs text-blue-700">建议：{{ $issue['suggestion'] }}</span>@endif
                        </span>
                    </label>
                @empty
                    <div class="px-6 py-10 text-center text-sm font-semibold text-emerald-700">当前文章已通过全部质量检查。</div>
                @endforelse
            </div>

            @if ($repairableIssues->isNotEmpty() && auth('admin')->user()?->canManageProtectedWorkflows() && config('geoflow.content_production_pipeline_enabled', false))
                <div class="flex flex-col gap-3 border-t border-gray-200 bg-slate-50 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs text-gray-500">自动修复次数：{{ $repairAttempts }} / {{ \App\Services\GeoFlow\TargetedRepairService::MAX_ATTEMPTS }}。达到上限后必须转人工处理。</p>
                    <button @disabled($repairAttempts >= \App\Services\GeoFlow\TargetedRepairService::MAX_ATTEMPTS) class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:bg-gray-300">修复选中问题并重新检查</button>
                </div>
            @endif
        </form>
    @else
        <div class="px-6 py-10 text-center">
            <p class="text-sm font-semibold text-gray-700">{{ $currentArticleVersion ? '文章已组装，可以开始质量检查。' : '请先完成文章组装。' }}</p>
            <p class="mt-1 text-sm text-gray-500">报告会记录问题位置、严重程度、建议和修复结果。</p>
        </div>
    @endif

    @if ($production->qualityRepairAttempts->isNotEmpty())
        <div class="border-t border-gray-200">
            <div class="px-6 py-3 text-xs font-semibold uppercase text-gray-500">修复历史</div>
            <div class="divide-y divide-gray-100">
                @foreach ($production->qualityRepairAttempts as $attempt)
                    <div class="flex flex-col gap-1 px-6 py-3 text-sm sm:flex-row sm:items-center sm:justify-between">
                        <span>第 {{ $attempt->attempt }} 次 · {{ $attempt->status }} · {{ count($attempt->selected_issue_ids ?? []) }} 个问题</span>
                        <span class="text-xs text-gray-500">{{ $attempt->repairedArticleVersion ? '生成文章版本 #'.$attempt->repairedArticleVersion->version : ($attempt->error_message ?: '处理中') }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</section>
