@extends('admin.layouts.app')

@section('content')
    <div class="mx-auto max-w-5xl px-4 sm:px-0">
        <a href="{{ route('admin.content-groups.show', $contentGroup) }}" class="inline-flex items-center text-sm font-semibold text-gray-500 hover:text-gray-700">
            <i data-lucide="arrow-left" class="mr-2 h-4 w-4"></i>返回内容组
        </a>
        <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">编辑{{ $platform['label'] ?? $variant->platform }}版本</h1>
                <p class="mt-2 text-sm text-gray-600">当前 V{{ $variant->version }}。保存会新增不可变版本，并重新进入审核。</p>
            </div>
            <span class="rounded-full bg-violet-50 px-3 py-1 text-xs font-semibold text-violet-700">{{ $platform['style'] ?? '' }}</span>
        </div>

        <form method="POST" action="{{ route('admin.content-groups.variants.update', [$contentGroup, $variant]) }}" class="mt-8 space-y-6 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            @csrf
            @method('PUT')
            <input type="hidden" name="current_version" value="{{ $variant->version }}">
            <div>
                <label for="title" class="block text-sm font-semibold text-gray-700">标题</label>
                <input id="title" name="title" value="{{ old('title', $variant->title) }}" maxlength="500" required class="mt-2 block w-full rounded-md border-gray-300 shadow-sm focus:border-violet-500 focus:ring-violet-500">
                <p class="mt-1 text-xs text-gray-500">平台建议不超过 {{ $platform['title_max_chars'] ?? 500 }} 字。</p>
            </div>
            <div>
                <label for="excerpt" class="block text-sm font-semibold text-gray-700">摘要</label>
                <textarea id="excerpt" name="excerpt" rows="3" maxlength="2000" class="mt-2 block w-full rounded-md border-gray-300 shadow-sm focus:border-violet-500 focus:ring-violet-500">{{ old('excerpt', $variant->excerpt) }}</textarea>
            </div>
            <div>
                <label for="content" class="block text-sm font-semibold text-gray-700">正文</label>
                <textarea id="content" name="content" rows="24" maxlength="100000" required class="mt-2 block w-full rounded-md border-gray-300 font-mono text-sm leading-7 shadow-sm focus:border-violet-500 focus:ring-violet-500">{{ old('content', $variant->content) }}</textarea>
                <p class="mt-1 text-xs text-gray-500">平台建议 {{ $platform['content_min_chars'] ?? '—' }}–{{ $platform['content_max_chars'] ?? '—' }} 字。</p>
            </div>
            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label for="tags" class="block text-sm font-semibold text-gray-700">标签</label>
                    <textarea id="tags" name="tags" rows="4" class="mt-2 block w-full rounded-md border-gray-300 shadow-sm focus:border-violet-500 focus:ring-violet-500">{{ old('tags', implode("\n", $variant->tags ?? [])) }}</textarea>
                    <p class="mt-1 text-xs text-gray-500">每行一个，最多 20 个。</p>
                </div>
                <div>
                    <label for="image_requirements" class="block text-sm font-semibold text-gray-700">图片要求</label>
                    <textarea id="image_requirements" name="image_requirements" rows="4" class="mt-2 block w-full rounded-md border-gray-300 shadow-sm focus:border-violet-500 focus:ring-violet-500">{{ old('image_requirements', implode("\n", $variant->image_requirements ?? [])) }}</textarea>
                    <p class="mt-1 text-xs text-gray-500">每行一个，最多 10 项。</p>
                </div>
            </div>
            <div class="flex justify-end gap-3 border-t border-gray-100 pt-5">
                <a href="{{ route('admin.content-groups.show', $contentGroup) }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">取消</a>
                <button type="submit" class="rounded-md bg-violet-600 px-5 py-2 text-sm font-semibold text-white hover:bg-violet-700">保存为新版本</button>
            </div>
        </form>

        @if ($variant->versions->isNotEmpty())
            <div class="mt-8 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="font-semibold text-gray-900">版本历史</h2>
                <ol class="mt-4 space-y-3">
                    @foreach ($variant->versions as $version)
                        <li class="rounded-md bg-gray-50 px-4 py-3 text-sm text-gray-600">
                            V{{ $version->version }} · {{ $version->change_type === 'manual_edit' ? '人工编辑' : 'AI 生成' }} · {{ $version->created_at?->format('Y-m-d H:i') }} · {{ $version->creator?->username ?: '系统' }}
                            <div class="mt-1 font-medium text-gray-900">{{ $version->title }}</div>
                        </li>
                    @endforeach
                </ol>
            </div>
        @endif
    </div>
@endsection
