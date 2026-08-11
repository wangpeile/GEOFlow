@extends('admin.layouts.app')

@section('content')
    <div class="flex flex-col gap-6 px-4 sm:px-0">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <a href="{{ route('admin.content-productions.index') }}" class="text-sm font-semibold text-blue-600 hover:text-blue-800">← 返回内容生产工作台</a>
                <h1 class="mt-3 text-2xl font-bold text-gray-900">写作规则</h1>
                <p class="mt-1 text-sm leading-6 text-gray-600">集中管理语言、篇幅、结构、引用、知识库和品牌要求。每次保存都会生成不可修改的新版本。</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.article-types.index') }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">文章类型</a>
                <form method="POST" action="{{ route('admin.writing-rules.install-presets') }}">
                    @csrf
                    <button class="rounded-md border border-blue-200 bg-blue-50 px-4 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-100">安装系统预设</button>
                </form>
                <a href="{{ route('admin.writing-rules.create') }}" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-700">新建规则</a>
            </div>
        </div>

        <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50"><tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">规则</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">文章类型</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">版本 / 状态</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">更新人</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">操作</th>
                    </tr></thead>
                    <tbody class="divide-y divide-gray-100">
                    @forelse ($rules as $rule)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4"><div class="font-semibold text-gray-900">{{ $rule->name }}</div><div class="mt-1 max-w-xl truncate text-xs text-gray-500">{{ $rule->description ?: '暂无说明' }}</div></td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ $rule->articleType?->name ?: '未分类' }}</td>
                            <td class="px-6 py-4"><span class="text-sm font-semibold text-gray-700">v{{ $rule->current_version }}</span><span class="ml-2 rounded-full px-2 py-1 text-xs font-semibold {{ $rule->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600' }}">{{ $rule->is_active ? '启用' : '停用' }}</span></td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ $rule->createdBy?->display_name ?: $rule->createdBy?->username ?: '系统' }}</td>
                            <td class="px-6 py-4 text-right text-sm"><a href="{{ route('admin.writing-rules.show', $rule) }}" class="font-semibold text-blue-600 hover:text-blue-800">查看</a><a href="{{ route('admin.writing-rules.edit', $rule) }}" class="ml-4 font-semibold text-blue-600 hover:text-blue-800">编辑</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-6 py-12 text-center text-sm text-gray-500">还没有写作规则，可安装系统预设或新建规则。</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if ($rules->hasPages())<div class="border-t border-gray-200 px-6 py-4">{{ $rules->links() }}</div>@endif
        </div>
    </div>
@endsection
