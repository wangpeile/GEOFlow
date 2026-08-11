@extends('admin.layouts.app')

@php($editing = $articleType !== null)
@php($defaults = $articleType?->default_settings ?? [])

@section('content')
    <div class="mx-auto flex max-w-3xl flex-col gap-6 px-4 sm:px-0"><div><a href="{{ route('admin.article-types.index') }}" class="text-sm font-semibold text-blue-600 hover:text-blue-800">← 返回文章类型</a><h1 class="mt-3 text-2xl font-bold text-gray-900">{{ $editing ? '编辑文章类型' : '新建文章类型' }}</h1></div>
        <form method="POST" action="{{ $editing ? route('admin.article-types.update', $articleType) : route('admin.article-types.store') }}" class="grid grid-cols-1 gap-5 rounded-lg border border-gray-200 bg-white p-6 shadow-sm sm:grid-cols-2">@csrf @if ($editing) @method('PUT') @endif
            <label class="flex flex-col gap-2"><span class="text-sm font-semibold text-gray-700">名称 *</span><input name="name" required maxlength="255" value="{{ old('name', $articleType?->name) }}" class="rounded-md border-gray-300 text-sm"></label>
            <label class="flex flex-col gap-2"><span class="text-sm font-semibold text-gray-700">编码 *</span><input name="code" required maxlength="80" value="{{ old('code', $articleType?->code) }}" class="rounded-md border-gray-300 text-sm" placeholder="例如 product_review"></label>
            <label class="sm:col-span-2 flex flex-col gap-2"><span class="text-sm font-semibold text-gray-700">说明</span><textarea name="description" rows="3" maxlength="2000" class="rounded-md border-gray-300 text-sm">{{ old('description', $articleType?->description) }}</textarea></label>
            <label class="flex flex-col gap-2"><span class="text-sm font-semibold text-gray-700">默认最少字数</span><input type="number" name="default_min_words" min="300" max="10000" value="{{ old('default_min_words', $defaults['min_words'] ?? 1000) }}" class="rounded-md border-gray-300 text-sm"></label>
            <label class="flex flex-col gap-2"><span class="text-sm font-semibold text-gray-700">默认最多字数</span><input type="number" name="default_max_words" min="300" max="10000" value="{{ old('default_max_words', $defaults['max_words'] ?? 2000) }}" class="rounded-md border-gray-300 text-sm"></label>
            <label class="flex flex-col gap-2"><span class="text-sm font-semibold text-gray-700">默认最少小标题</span><input type="number" name="default_min_headings" min="2" max="20" value="{{ old('default_min_headings', $defaults['min_headings'] ?? 5) }}" class="rounded-md border-gray-300 text-sm"></label>
            <label class="flex flex-col gap-2"><span class="text-sm font-semibold text-gray-700">默认最多小标题</span><input type="number" name="default_max_headings" min="2" max="20" value="{{ old('default_max_headings', $defaults['max_headings'] ?? 8) }}" class="rounded-md border-gray-300 text-sm"></label>
            <label class="sm:col-span-2 flex flex-col gap-2"><span class="text-sm font-semibold text-gray-700">推荐结构</span><textarea name="structure_notes" rows="5" maxlength="5000" class="rounded-md border-gray-300 text-sm" placeholder="例如：摘要、问题背景、分步说明、常见问题、结论">{{ old('structure_notes', $defaults['structure_notes'] ?? '') }}</textarea></label>
            <label class="sm:col-span-2 flex items-center gap-2"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', $articleType?->is_active ?? true)) class="rounded border-gray-300 text-blue-600"><span class="text-sm font-semibold text-gray-700">启用这类文章</span></label>
            <div class="sm:col-span-2 flex justify-end gap-3 border-t border-gray-100 pt-5"><a href="{{ route('admin.article-types.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700">取消</a><button class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">保存</button></div>
        </form>
    </div>
@endsection
