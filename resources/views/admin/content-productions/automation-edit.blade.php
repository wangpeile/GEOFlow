@extends('admin.layouts.app')

@section('content')
    <div class="mx-auto flex max-w-5xl flex-col gap-6 px-4 sm:px-0">
        <div>
            <a href="{{ route('admin.content-automations.index') }}" class="text-sm font-semibold text-blue-600">← 返回计划列表</a>
            <h1 class="mt-3 text-2xl font-bold text-gray-900">配置：{{ $task->name }}</h1>
            <p class="mt-1 text-sm text-gray-600">标准模式会自动选择首个标题和大纲，完成正文与质量检查后停在待审核状态。</p>
        </div>

        <form method="POST" action="{{ route('admin.content-automations.update', $task) }}" class="space-y-6 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            @csrf @method('PUT')
            <div><label class="text-sm font-semibold text-gray-800">写作规则</label><select name="writing_rule_id" class="mt-2 w-full rounded-md border-gray-300">
                @foreach($writingRules as $rule)<option value="{{ $rule->id }}" @selected((int)old('writing_rule_id', $task->writing_rule_id) === $rule->id)>{{ $rule->name }}</option>@endforeach
            </select></div>
            <div><label class="text-sm font-semibold text-gray-800">固定规则版本</label><select name="writing_rule_version_id" class="mt-2 w-full rounded-md border-gray-300">
                @foreach($writingRules as $rule)@foreach($rule->versions as $version)<option value="{{ $version->id }}" @selected((int)old('writing_rule_version_id', $task->writing_rule_version_id) === $version->id)>{{ $rule->name }} · V{{ $version->version }}</option>@endforeach @endforeach
            </select></div>
            <div><label class="text-sm font-semibold text-gray-800">选题（每行一个）</label><textarea name="topics" rows="8" class="mt-2 w-full rounded-md border-gray-300" placeholder="例如：企业视频会议怎么选">{{ old('topics', implode("\n", data_get($task->automation_settings, 'topics', []))) }}</textarea></div>
            <div class="grid gap-5 sm:grid-cols-2">
                <div><label class="text-sm font-semibold text-gray-800">时区</label><input name="automation_timezone" value="{{ old('automation_timezone', $task->automation_timezone ?: 'Asia/Shanghai') }}" class="mt-2 w-full rounded-md border-gray-300"></div>
                <div><label class="text-sm font-semibold text-gray-800">每日生成篇数</label><input type="number" min="1" max="30" name="daily_production_limit" value="{{ old('daily_production_limit', $task->daily_production_limit ?: 1) }}" class="mt-2 w-full rounded-md border-gray-300"></div>
                <div><label class="text-sm font-semibold text-gray-800">最大并发</label><input type="number" min="1" max="5" name="max_production_concurrency" value="{{ old('max_production_concurrency', $task->max_production_concurrency ?: 1) }}" class="mt-2 w-full rounded-md border-gray-300"></div>
                <div><label class="text-sm font-semibold text-gray-800">失败策略</label><select name="production_failure_policy" class="mt-2 w-full rounded-md border-gray-300"><option value="continue" @selected(old('production_failure_policy', $task->production_failure_policy) === 'continue')>记录失败并继续</option><option value="pause" @selected(old('production_failure_policy', $task->production_failure_policy) === 'pause')>失败后暂停计划</option></select></div>
                <div><label class="text-sm font-semibold text-gray-800">每日 Token 预算（可选）</label><input type="number" name="daily_token_budget" value="{{ old('daily_token_budget', $task->daily_token_budget) }}" class="mt-2 w-full rounded-md border-gray-300"></div>
            </div>
            <div class="rounded-md bg-blue-50 p-4 text-sm text-blue-800">当前版本只允许输出 WordPress 草稿；自动发布将在发布集成迭代单独开启。</div>
            @if($errors->any())<div class="rounded-md bg-red-50 p-4 text-sm text-red-700">{{ $errors->first() }}</div>@endif
            <button class="rounded-md bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">保存并启用</button>
        </form>

        <div class="flex flex-wrap gap-3">
            <form method="POST" action="{{ route('admin.content-automations.run-now', $task) }}">@csrf<button class="rounded-md border border-blue-300 bg-white px-4 py-2 text-sm font-semibold text-blue-700">立即运行一次</button></form>
            <form method="POST" action="{{ route($task->schedule_enabled ? 'admin.content-automations.pause' : 'admin.content-automations.resume', $task) }}">@csrf<button class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700">{{ $task->schedule_enabled ? '暂停计划' : '恢复计划' }}</button></form>
            <form method="POST" action="{{ route('admin.content-automations.fallback', $task) }}">@csrf<button class="rounded-md border border-amber-300 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-800">回退旧版任务模式</button></form>
        </div>

        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-200 px-6 py-4">
                <h2 class="font-semibold text-gray-900">最近运行</h2>
                <p class="mt-1 text-sm text-gray-500">失败任务可从已经完成的资料、标题、大纲和章节处继续，不重复生成成功内容。</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50"><tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500">选题</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500">状态 / 尝试</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500">结果</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500">操作</th>
                    </tr></thead>
                    <tbody class="divide-y divide-gray-100">
                    @forelse($task->taskSchedules as $schedule)
                        <tr>
                            <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $schedule->topic }}</td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ $schedule->status->value }} · 第 {{ $schedule->attempt_count }} 次</td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                @if($schedule->contentProduction)
                                    <a class="font-semibold text-blue-600" href="{{ route('admin.content-productions.show', $schedule->contentProduction) }}">查看文章工作单</a>
                                @endif
                                @if($schedule->error_message)<div class="mt-1 max-w-xl text-xs text-red-600">{{ $schedule->error_message }}</div>@endif
                            </td>
                            <td class="px-6 py-4 text-right">
                                @if($schedule->status->value === 'failed')
                                    <form method="POST" action="{{ route('admin.content-automations.retry', [$task, $schedule]) }}">@csrf<button class="rounded-md border border-red-300 bg-white px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50">从失败处继续</button></form>
                                @else
                                    <span class="text-xs text-gray-400">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-6 py-10 text-center text-sm text-gray-500">暂无运行记录。</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
