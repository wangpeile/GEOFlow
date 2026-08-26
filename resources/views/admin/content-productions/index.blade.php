@extends('admin.layouts.app')

@section('content')
    <div class="flex flex-col gap-6 px-4 sm:px-0">
        <section class="rounded-2xl border border-blue-100 bg-gradient-to-br from-blue-50 via-white to-violet-50 p-6 shadow-sm sm:p-8">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-2xl">
                    <p class="text-sm font-semibold text-blue-700">内容生产</p>
                    <h1 class="mt-2 text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl">从一篇主文章开始，完成研究、写作与发布准备</h1>
                    <p class="mt-3 text-sm leading-6 text-gray-600">一张文章工作单对应一篇主文章；完成后再建立发布包，为 WordPress 与各内容平台生成适配版本。</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('admin.content-topics.index') }}" class="inline-flex items-center justify-center rounded-md border border-teal-300 bg-white px-4 py-2 text-sm font-semibold text-teal-700 hover:bg-teal-50"><i data-lucide="folder-kanban" class="mr-2 h-4 w-4"></i>内容专题</a>
                    <a href="{{ route('admin.content-groups.index') }}" class="inline-flex items-center justify-center rounded-md border border-violet-300 bg-white px-4 py-2 text-sm font-semibold text-violet-700 hover:bg-violet-50"><i data-lucide="layers-3" class="mr-2 h-4 w-4"></i>发布包</a>
                    <a href="{{ route('admin.content-automations.index') }}" class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"><i data-lucide="calendar-clock" class="mr-2 h-4 w-4"></i>生产计划</a>
                    <a href="{{ route('admin.content-productions.create') }}" class="inline-flex items-center justify-center rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-700"><i data-lucide="plus" class="mr-2 h-4 w-4"></i>新建文章</a>
                </div>
            </div>
        </section>

        <section aria-labelledby="work-queue-heading">
            <div class="mb-4 flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                <div><h2 id="work-queue-heading" class="text-lg font-semibold text-gray-900">工作队列</h2><p class="mt-1 text-sm text-gray-500">先处理待继续和待审核的文章；完成后可进入发布包生成与多平台改写。</p></div>
                <a href="{{ route('admin.writing-rules.index') }}" class="text-sm font-semibold text-blue-600 hover:text-blue-800">管理写作规则</a>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($workQueues as $queue)
                    @php($tone = $queue['tone'])
                    <article class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                        <div class="flex items-start justify-between gap-3"><span class="inline-flex h-10 w-10 items-center justify-center rounded-lg {{ $tone === 'blue' ? 'bg-blue-50 text-blue-700' : ($tone === 'amber' ? 'bg-amber-50 text-amber-700' : ($tone === 'violet' ? 'bg-violet-50 text-violet-700' : 'bg-emerald-50 text-emerald-700')) }}"><i data-lucide="{{ $queue['icon'] }}" class="h-5 w-5"></i></span><span class="text-2xl font-bold text-gray-900">{{ $queue['count'] }}</span></div>
                        <h3 class="mt-4 font-semibold text-gray-900">{{ $queue['label'] }}</h3><p class="mt-1 text-sm leading-6 text-gray-500">{{ $queue['description'] }}</p>
                    </article>
                @endforeach
            </div>
        </section>

        <section aria-labelledby="creation-mode-heading" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"><div><h2 id="creation-mode-heading" class="text-lg font-semibold text-gray-900">如何开始</h2><p class="mt-1 text-sm text-gray-500">选择适合当前任务的入口；不会创建无法执行的任务。</p></div><a href="{{ route('admin.content-productions.create') }}" class="text-sm font-semibold text-blue-600 hover:text-blue-800">进入新建文章 →</a></div>
            <div class="mt-5 grid grid-cols-1 gap-3 lg:grid-cols-4">
                @foreach ($creationModes as $mode)
                    <a href="{{ $mode['route'] }}" class="group rounded-lg border border-gray-200 p-4 transition hover:border-blue-300 hover:bg-blue-50/50"><div class="flex items-center gap-3"><span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-slate-100 text-slate-600 group-hover:bg-blue-100 group-hover:text-blue-700"><i data-lucide="{{ $mode['icon'] }}" class="h-4 w-4"></i></span><h3 class="font-semibold text-gray-900">{{ $mode['name'] }}</h3></div><p class="mt-3 text-sm leading-5 text-gray-500">{{ $mode['description'] }}</p></a>
                @endforeach
            </div>
        </section>

        <section aria-labelledby="recent-work-heading">
            <div><h2 id="recent-work-heading" class="text-lg font-semibold text-gray-900">最近的文章工作单</h2><p class="mt-1 text-sm text-gray-500">打开后只聚焦当前阶段，已完成的资料与版本仍会被完整保留。</p></div>
            <div class="mt-4 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm"><div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200"><thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">文章工作单</th><th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">模式 / 语言</th><th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">当前阶段</th><th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">状态</th><th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">操作</th></tr></thead><tbody class="divide-y divide-gray-100">
                        @forelse ($productions as $production)
                            <tr class="hover:bg-gray-50"><td class="px-6 py-4"><div class="font-semibold text-gray-900">{{ $production->name }}</div><div class="mt-1 max-w-xl truncate text-xs text-gray-500">{{ $production->topic }}</div><div class="mt-1 text-xs text-gray-400">{{ $production->createdBy?->name ?: '系统' }} · {{ $production->created_at?->format('Y-m-d H:i') }}</div></td><td class="px-6 py-4 text-sm text-gray-600">{{ $production->mode->value }} / {{ $production->language }}</td><td class="px-6 py-4 text-sm text-gray-600">{{ $production->current_stage?->value ?: '—' }}</td><td class="px-6 py-4"><span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ $production->status->value }}</span>@if ($production->last_error_message)<div class="mt-1 max-w-xs truncate text-xs text-red-600">{{ $production->last_error_message }}</div>@endif</td><td class="px-6 py-4 text-right"><a href="{{ route('admin.content-productions.show', $production) }}" class="text-sm font-semibold text-blue-600 hover:text-blue-800">打开工作台</a></td></tr>
                        @empty
                            <tr><td colspan="5" class="px-6 py-12 text-center text-sm text-gray-500">还没有文章工作单。现在新建一篇文章，开始第一轮生产。</td></tr>
                        @endforelse
                    </tbody></table></div>@if ($productions->hasPages())<div class="border-t border-gray-200 px-6 py-4">{{ $productions->links() }}</div>@endif</div>
        </section>
    </div>
@endsection
