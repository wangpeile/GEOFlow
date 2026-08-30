@extends('admin.layouts.app')

@section('content')
    <div class="flex flex-col gap-6 px-4 sm:px-0">
        <section class="rounded-2xl border border-emerald-100 bg-gradient-to-br from-emerald-50 via-white to-blue-50 p-6 sm:p-8">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-3xl"><p class="text-sm font-semibold text-emerald-700">内容中心</p><h1 class="mt-2 text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl">审核和管理已固化的主文章</h1><p class="mt-3 text-sm leading-6 text-gray-600">这里仅管理已经从文章工作单固化，或手工创建的主文章。研究、标题、大纲、章节与草稿反馈仍在内容生产工作台完成。</p></div>
                <div class="flex flex-wrap gap-2"><a href="{{ route('admin.content-productions.create') }}" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">新建文章工作单</a><a href="{{ route('admin.articles.create') }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">手工建文</a></div>
            </div>
        </section>
        <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            @foreach (['all' => ['全部', 'bg-slate-50 text-slate-800'], 'review' => ['待审核', 'bg-amber-50 text-amber-800'], 'draft' => ['草稿', 'bg-blue-50 text-blue-800'], 'published' => ['已发布', 'bg-emerald-50 text-emerald-800']] as $key => [$label, $class])
                <a href="{{ route('admin.content-center.index', ['status' => $key, 'q' => $query ?: null]) }}" class="rounded-lg p-4 {{ $class }} {{ $status === $key ? 'ring-2 ring-blue-400' : '' }}"><div class="text-xs font-semibold">{{ $label }}</div><div class="mt-1 text-2xl font-bold">{{ $counts[$key] }}</div></a>
            @endforeach
        </section>
        <form method="GET" class="flex gap-3"><input type="hidden" name="status" value="{{ $status }}"><input name="q" value="{{ $query }}" class="min-w-0 flex-1 rounded-md border-gray-300 text-sm" placeholder="搜索主文章标题"><button class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700">搜索</button></form>
        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm"><div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200"><thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">主文章</th><th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">来源</th><th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">审核 / 状态</th><th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">更新时间</th><th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">操作</th></tr></thead><tbody class="divide-y divide-gray-100">
            @forelse ($articles as $article)
                @php($workOrder = $article->contentProductions->sortByDesc('id')->first())
                <tr class="hover:bg-gray-50"><td class="px-6 py-4"><div class="max-w-xl font-semibold text-gray-900">{{ $article->title }}</div><div class="mt-1 text-xs text-gray-500">{{ $article->original_keyword ?: '—' }}</div></td><td class="px-6 py-4 text-sm text-gray-600">@if($workOrder)<a href="{{ route('admin.content-productions.show', $workOrder) }}" class="font-semibold text-blue-600 hover:text-blue-800">{{ $workOrder->name }}</a>@else 手工创建 @endif</td><td class="px-6 py-4 text-sm text-gray-600"><span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-800">{{ $article->review_status }}</span><span class="ml-1 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ $article->status }}</span></td><td class="px-6 py-4 text-sm text-gray-500">{{ $article->updated_at?->format('Y-m-d H:i') }}</td><td class="px-6 py-4 text-right"><div class="flex justify-end gap-3"><a href="{{ route('admin.articles.edit', $article) }}" class="text-sm font-semibold text-blue-600">编辑</a>@if($article->contentGroup)<a href="{{ route('admin.content-groups.show', $article->contentGroup) }}" class="text-sm font-semibold text-violet-700">发布包</a>@elseif(in_array($article->review_status, ['approved', 'auto_approved'], true))<form method="POST" action="{{ route('admin.content-groups.store', $article) }}">@csrf<button class="text-sm font-semibold text-violet-700">建立发布包</button></form>@else<span class="text-xs font-medium text-gray-400">审核后可建发布包</span>@endif</div></td></tr>
            @empty
                <tr><td colspan="5" class="px-6 py-12 text-center text-sm text-gray-500">还没有主文章。先在内容生产中完成工作单、质量检查与主文章固化。</td></tr>
            @endforelse
        </tbody></table></div>@if($articles->hasPages())<div class="border-t border-gray-200 px-6 py-4">{{ $articles->links() }}</div>@endif</section>
    </div>
@endsection
