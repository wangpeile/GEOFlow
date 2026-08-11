@extends('admin.layouts.app')

@section('content')
    <div class="flex flex-col gap-6 px-4 sm:px-0">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">内容生产工作台</h1>
                <p class="mt-1 text-sm leading-6 text-gray-600">选择创作方式，或继续处理已有内容项目。</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.content-groups.index') }}" class="inline-flex items-center justify-center rounded-md border border-violet-300 bg-white px-4 py-2 text-sm font-semibold text-violet-700 hover:bg-violet-50">多平台改写</a>
                <a href="{{ route('admin.writing-rules.index') }}" class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">写作规则</a>
                @if (config('geoflow.content_production_pipeline_enabled', false))
                    <a href="{{ route('admin.content-productions.create') }}" class="inline-flex items-center justify-center rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-700">
                        <i data-lucide="plus" class="mr-2 h-4 w-4"></i>
                        开始可视化创作
                    </a>
                @endif
            </div>
        </div>

        <section aria-labelledby="creation-mode-heading">
            <div class="mb-4">
                <h2 id="creation-mode-heading" class="text-lg font-semibold text-gray-900">选择创作方式</h2>
                <p class="mt-1 text-sm text-gray-500">未开放的模式会明确标注，不会创建无法执行的任务。</p>
            </div>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                @foreach ($creationModes as $mode)
                    <article class="flex min-h-64 flex-col rounded-xl border {{ $mode['status'] === 'available' ? 'border-blue-200 bg-blue-50/40' : 'border-gray-200 bg-white' }} p-5 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <span class="inline-flex h-11 w-11 items-center justify-center rounded-lg {{ $mode['status'] === 'available' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-600' }}">
                                <i data-lucide="{{ $mode['icon'] }}" class="h-5 w-5"></i>
                            </span>
                            <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $mode['status'] === 'available' ? 'bg-emerald-100 text-emerald-700' : ($mode['status'] === 'basic' ? 'bg-blue-100 text-blue-700' : 'bg-amber-100 text-amber-700') }}">
                                {{ $mode['status'] === 'available' ? '已开放' : ($mode['status'] === 'basic' ? '基础版可用' : '即将开放') }}
                            </span>
                        </div>
                        <h3 class="mt-5 text-base font-semibold text-gray-900">{{ $mode['name'] }}</h3>
                        <p class="mt-2 grow text-sm leading-6 text-gray-600">{{ $mode['description'] }}</p>
                        @if (isset($mode['prerequisite']))
                            <p class="mt-4 rounded-md bg-amber-50 px-3 py-2 text-xs leading-5 text-amber-800">{{ $mode['prerequisite'] }}</p>
                        @endif
                        @if (isset($mode['route']))
                            <a href="{{ $mode['route'] }}" class="mt-5 inline-flex items-center justify-center rounded-md {{ $mode['status'] === 'available' ? 'bg-blue-600 text-white hover:bg-blue-700' : 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50' }} px-4 py-2 text-sm font-semibold">
                                {{ $mode['status'] === 'available' ? '开始创作' : '打开基础编辑器' }}
                            </a>
                        @else
                            <button type="button" disabled class="mt-5 cursor-not-allowed rounded-md bg-gray-100 px-4 py-2 text-sm font-semibold text-gray-400">暂不可用</button>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>

        <div>
            <h2 class="text-lg font-semibold text-gray-900">最近的创作项目</h2>
            <p class="mt-1 text-sm text-gray-500">打开项目后可从上次未完成的步骤继续。</p>
        </div>

        <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">项目</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">模式 / 语言</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">当前阶段</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">状态</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">操作</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                    @forelse ($productions as $production)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <div class="font-semibold text-gray-900">{{ $production->name }}</div>
                                <div class="mt-1 max-w-xl truncate text-xs text-gray-500">{{ $production->topic }}</div>
                                <div class="mt-1 text-xs text-gray-400">{{ $production->createdBy?->name ?: '系统' }} · {{ $production->created_at?->format('Y-m-d H:i') }}</div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ $production->mode->value }} / {{ $production->language }}</td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ $production->current_stage?->value ?: '—' }}</td>
                            <td class="px-6 py-4">
                                <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ $production->status->value }}</span>
                                @if ($production->last_error_message)
                                    <div class="mt-1 max-w-xs truncate text-xs text-red-600">{{ $production->last_error_message }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('admin.content-productions.show', $production) }}" class="text-sm font-semibold text-blue-600 hover:text-blue-800">继续创作</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-sm text-gray-500">还没有内容生产项目。</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if ($productions->hasPages())
                <div class="border-t border-gray-200 px-6 py-4">{{ $productions->links() }}</div>
            @endif
        </div>
    </div>
@endsection
