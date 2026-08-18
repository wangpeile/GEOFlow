@extends('admin.layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900">内容生产运营看板</h1>
            <p class="mt-1 text-sm text-gray-500">统计均可下钻到内容生产项目与文章，时间口径为 {{ $metrics['period']['timezone'] }}。</p>
        </div>
        <a href="{{ route('admin.content-productions.index') }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700">返回内容生产</a>
    </div>

    <form method="GET" class="flex flex-wrap gap-3 rounded-lg bg-white p-4 shadow ring-1 ring-gray-200">
        <input type="date" name="from" value="{{ $metrics['period']['from'] }}" class="rounded-md border-gray-300 text-sm">
        <input type="date" name="to" value="{{ $metrics['period']['to'] }}" class="rounded-md border-gray-300 text-sm">
        <button class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white">查询</button>
        <a href="{{ route('admin.content-operations.json', request()->only('from', 'to')) }}" class="rounded-md border border-blue-200 px-4 py-2 text-sm font-medium text-blue-700">只读 JSON</a>
    </form>

    <div class="grid gap-4 md:grid-cols-4">
        @foreach ([
            ['内容项目', $metrics['productions']['total']],
            ['质量报告', $metrics['quality']['reports']],
            ['平台稿件', $metrics['platform_variants']['total']],
            ['已审核平台稿', $metrics['platform_variants']['approved']],
        ] as [$label, $value])
            <div class="rounded-lg bg-white p-5 shadow ring-1 ring-gray-200"><div class="text-sm text-gray-500">{{ $label }}</div><div class="mt-2 text-3xl font-semibold text-gray-900">{{ $value }}</div></div>
        @endforeach
    </div>

    <div class="overflow-hidden rounded-lg bg-white shadow ring-1 ring-gray-200">
        <div class="border-b border-gray-200 px-5 py-4 font-semibold text-gray-900">最近内容生产</div>
        <div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50"><tr><th class="px-5 py-3 text-left">项目</th><th class="px-5 py-3 text-left">主题</th><th class="px-5 py-3 text-left">状态</th><th class="px-5 py-3 text-left">时间</th></tr></thead>
            <tbody class="divide-y divide-gray-100">
            @forelse ($metrics['recent'] as $item)
                <tr><td class="px-5 py-3"><a class="text-blue-600" href="{{ route('admin.content-productions.show', $item['production_id']) }}">#{{ $item['production_id'] }} {{ $item['name'] }}</a></td><td class="px-5 py-3">{{ $item['topic'] }}</td><td class="px-5 py-3">{{ $item['status'] }}</td><td class="px-5 py-3">{{ $item['created_at'] }}</td></tr>
            @empty
                <tr><td colspan="4" class="px-5 py-10 text-center text-gray-500">当前时间范围暂无数据。</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>
</div>
@endsection
