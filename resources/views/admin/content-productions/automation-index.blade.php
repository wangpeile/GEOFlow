@extends('admin.layouts.app')

@section('content')
    <div class="flex flex-col gap-6 px-4 sm:px-0">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">生产计划</h1>
                <p class="mt-1 text-sm text-gray-600">生产计划每天按时创建一张文章工作单；可以绑定内容专题，也可以使用本计划的独立选题池。默认只保存为 WordPress 草稿。</p>
            </div>
            <a href="{{ route('admin.content-productions.index') }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">返回内容生产</a>
        </div>

        <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50"><tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500">任务</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500">模式 / 状态</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500">运行统计</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500">最近状态</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500">操作</th>
                    </tr></thead>
                    <tbody class="divide-y divide-gray-100">
                    @forelse($tasks as $task)
                        <tr>
                            <td class="px-6 py-4"><div class="font-semibold text-gray-900">{{ $task->name }}</div><div class="mt-1 text-xs text-gray-500">{{ $task->contentTopic?->name ?: '独立选题池' }} · 每日 {{ $task->daily_production_limit ?: 1 }} 篇 · 已创建 {{ $task->content_productions_count }} 张工作单</div></td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ $task->pipeline_mode?->value ?: 'legacy' }} · {{ $task->schedule_enabled ? '运行中' : '已暂停' }}</td>
                            @php
                                $finishedRuns = $task->successful_runs_count + $task->failed_runs_count;
                                $successRate = $finishedRuns > 0 ? round($task->successful_runs_count * 100 / $finishedRuns) : null;
                                $averageSeconds = $task->average_duration_ms ? round($task->average_duration_ms / 1000) : null;
                            @endphp
                            <td class="px-6 py-4 text-sm text-gray-600">
                                <div><span class="font-semibold text-emerald-700">成功 {{ $task->successful_runs_count }}</span> · <span class="font-semibold text-red-700">失败 {{ $task->failed_runs_count }}</span></div>
                                <div class="mt-1 text-xs text-gray-500">成功率 {{ $successRate === null ? '—' : $successRate.'%' }} · 平均耗时 {{ $averageSeconds === null ? '—' : $averageSeconds.' 秒' }}</div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">下次：{{ $task->next_run_at?->format('Y-m-d H:i') ?: '未安排' }}@if($task->last_error_message)<div class="mt-1 max-w-md truncate text-xs text-red-600" title="{{ $task->last_error_message }}">{{ $task->last_error_message }}</div>@endif</td>
                            <td class="px-6 py-4 text-right"><a href="{{ route('admin.content-automations.edit', $task) }}" class="text-sm font-semibold text-blue-600 hover:text-blue-800">配置</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-6 py-12 text-center text-sm text-gray-500">还没有可配置的任务，请先在任务管理中创建任务。</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if($tasks->hasPages())<div class="border-t border-gray-200 px-6 py-4">{{ $tasks->links() }}</div>@endif
        </div>
    </div>
@endsection
