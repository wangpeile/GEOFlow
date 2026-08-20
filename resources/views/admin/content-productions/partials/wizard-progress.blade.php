<section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm" aria-labelledby="workbench-progress-heading">
    <div class="flex flex-col gap-3 border-b border-gray-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 id="workbench-progress-heading" class="font-semibold text-gray-900">文章工作台</h2>
            <p class="mt-1 text-sm text-gray-500">当前只展开一个工作阶段，避免在一页内同时处理所有创作任务。</p>
        </div>
        @if ($resumeStep && $activeWorkbenchStep !== $resumeStep['key'])
            <a href="{{ route('admin.content-productions.show', ['contentProduction' => $production, 'stage' => $resumeStep['key']]) }}" class="inline-flex shrink-0 items-center justify-center rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">继续：{{ $resumeStep['label'] }}<i data-lucide="arrow-right" class="ml-2 h-4 w-4"></i></a>
        @endif
    </div>
    <ol class="grid grid-cols-1 gap-px bg-gray-200 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($wizardSteps as $step)
            @php($isActive = $activeWorkbenchStep === $step['key'])
            <li class="bg-white">
                <a href="{{ route('admin.content-productions.show', ['contentProduction' => $production, 'stage' => $step['key']]) }}" class="flex min-h-24 items-center gap-3 px-4 py-3 transition hover:bg-gray-50 {{ $isActive ? 'bg-blue-50/70 ring-2 ring-inset ring-blue-500' : '' }}" @if ($isActive) aria-current="step" @endif>
                    <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-bold {{ $step['state'] === 'done' ? 'bg-emerald-100 text-emerald-700' : ($isActive ? 'bg-blue-600 text-white' : ($step['state'] === 'current' ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-500')) }}">
                        @if ($step['state'] === 'done')<i data-lucide="check" class="h-4 w-4"></i>@else{{ $step['number'] }}@endif
                    </span>
                    <span class="min-w-0"><span class="block text-sm font-semibold {{ $isActive ? 'text-blue-800' : 'text-gray-800' }}">{{ $step['label'] }}</span><span class="mt-0.5 block text-xs leading-5 text-gray-500">{{ $step['description'] }}</span></span>
                </a>
            </li>
        @endforeach
    </ol>
</section>
