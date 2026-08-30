@extends('admin.layouts.app')

@section('content')
    <div class="px-4 sm:px-0">
        <div class="mb-8 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">平台发布规则</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-gray-600">这里维护发布前检查和素材提示。默认规则是内部发布规范，不代表平台官方审核承诺；规则更新仅影响后续生成，已生成版本保留当时的规则快照。</p>
            </div>
            <a href="{{ route('admin.content-groups.index') }}" class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">返回发布包中心</a>
        </div>
        <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
            @foreach ($items as $item)
                @php($spec = $item['specification'])
                @php($rules = $item['resolved']['rules'])
                <article class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <div class="flex items-start justify-between gap-4">
                        <div><h2 class="text-lg font-semibold text-gray-900">{{ $rules['label'] ?? $item['platform'] }}</h2><p class="mt-1 text-sm text-gray-500">{{ $item['platform'] }}</p></div>
                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $spec?->status === 'review_needed' ? 'bg-amber-100 text-amber-800' : 'bg-emerald-50 text-emerald-700' }}">{{ $spec?->status === 'review_needed' ? '待复核' : '可用' }}</span>
                    </div>
                    <dl class="mt-5 grid grid-cols-2 gap-4 text-sm"><div><dt class="text-gray-500">规则版本</dt><dd class="mt-1 font-semibold">{{ $item['resolved']['version'] }}</dd></div><div><dt class="text-gray-500">规则来源</dt><dd class="mt-1 font-semibold">{{ $spec ? '已维护规格' : '内部默认规则' }}</dd></div><div><dt class="text-gray-500">标题上限</dt><dd class="mt-1 font-semibold">{{ $rules['title_max_chars'] ?? '—' }} 字</dd></div><div><dt class="text-gray-500">正文范围</dt><dd class="mt-1 font-semibold">{{ ($rules['content_min_chars'] ?? '—').'–'.($rules['content_max_chars'] ?? '—') }} 字</dd></div></dl>
                    @php($feedback = $item['feedback'])
                    @if ($feedback !== [])
                        <p class="mt-4 text-xs text-gray-500">发布反馈：已发布 {{ $feedback['published'] ?? 0 }} · 被拒 {{ $feedback['rejected'] ?? 0 }} · 人工调整 {{ $feedback['manual_adjusted'] ?? 0 }}</p>
                    @endif
                    <p class="mt-5 rounded-md bg-gray-50 p-3 text-sm leading-6 text-gray-600">{{ $rules['instructions'] ?? '' }}</p>
                    <a href="{{ route('admin.content-platform-specifications.edit', $item['platform']) }}" class="mt-5 inline-flex rounded-md bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-700">维护规则与来源</a>
                </article>
            @endforeach
        </div>
    </div>
@endsection
