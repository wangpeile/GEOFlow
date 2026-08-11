<section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm" aria-labelledby="wizard-progress-heading">
    <div class="flex flex-col gap-4 border-b border-gray-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 id="wizard-progress-heading" class="font-semibold text-gray-900">可视化创作向导</h2>
            <p class="mt-1 text-sm text-gray-500">系统已根据保存结果定位到下一步；已完成内容可随时返回查看和调整。</p>
        </div>
        @if ($resumeStep)
            <a href="#{{ $resumeStep['anchor'] }}" class="inline-flex shrink-0 items-center justify-center rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                继续：{{ $resumeStep['label'] }}
                <i data-lucide="arrow-down" class="ml-2 h-4 w-4"></i>
            </a>
        @endif
    </div>
    <ol class="grid grid-cols-1 gap-px bg-gray-200 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($wizardSteps as $step)
            <li class="bg-white">
                <a href="#{{ $step['anchor'] }}" class="flex min-h-20 items-center gap-3 px-4 py-3 hover:bg-gray-50" @if ($step['state'] === 'current') aria-current="step" @endif>
                    <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-bold {{ $step['state'] === 'done' ? 'bg-emerald-100 text-emerald-700' : ($step['state'] === 'current' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-500') }}">
                        @if ($step['state'] === 'done')
                            <i data-lucide="check" class="h-4 w-4"></i>
                        @else
                            {{ $step['number'] }}
                        @endif
                    </span>
                    <span>
                        <span class="block text-sm font-semibold {{ $step['state'] === 'current' ? 'text-blue-700' : 'text-gray-800' }}">{{ $step['label'] }}</span>
                        <span class="mt-0.5 block text-xs text-gray-500">{{ $step['state'] === 'done' ? '已保存' : ($step['state'] === 'current' ? '建议继续' : '等待上一步') }}</span>
                    </span>
                </a>
            </li>
        @endforeach
    </ol>
</section>
