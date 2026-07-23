@extends('admin.layouts.app')

@section('content')
    <div class="px-4 sm:px-0">
        <div class="mb-8 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0">
                <a href="{{ route('admin.content-groups.index') }}" class="inline-flex items-center text-sm font-semibold text-gray-500 hover:text-gray-700">
                    <i data-lucide="arrow-left" class="mr-2 h-4 w-4"></i>
                    {{ __('admin.content_groups.back_to_groups') }}
                </a>
                <h1 class="mt-3 text-2xl font-bold text-gray-900">{{ $contentGroup->name }}</h1>
                <p class="mt-2 text-sm text-gray-600">{{ __('admin.content_groups.detail_subtitle') }}</p>
            </div>
            @if ($contentGroup->mainArticle)
                <a href="{{ route('admin.articles.edit', ['articleId' => $contentGroup->mainArticle->id]) }}" class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                    <i data-lucide="file-pen-line" class="mr-2 h-4 w-4"></i>
                    {{ __('admin.content_groups.edit_main_article') }}
                </a>
            @endif
        </div>

        <div class="mb-8 grid grid-cols-1 gap-4 md:grid-cols-3">
            <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                <div class="text-sm font-medium text-gray-500">{{ __('admin.content_groups.main_article') }}</div>
                <div class="mt-2 font-semibold text-gray-900">{{ $contentGroup->mainArticle?->title ?: '—' }}</div>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                <div class="text-sm font-medium text-gray-500">{{ __('admin.content_groups.task') }}</div>
                <div class="mt-2 font-semibold text-gray-900">{{ $contentGroup->task?->name ?: '—' }}</div>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                <div class="text-sm font-medium text-gray-500">{{ __('admin.content_groups.platform_count') }}</div>
                <div class="mt-2 text-2xl font-bold text-gray-900">{{ $contentGroup->variants->count() }}</div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
            @foreach ($contentGroup->variants as $variant)
                @php($template = $platforms[$variant->platform] ?? [])
                <article class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-lg font-semibold text-gray-900">{{ $template['label'] ?? $variant->platform }}</h2>
                                <span class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700">{{ __('admin.content_groups.modes.'.($template['publishing_mode'] ?? 'manual')) }}</span>
                            </div>
                            <p class="mt-2 text-sm leading-6 text-gray-600">{{ $template['style'] ?? '' }}</p>
                        </div>
                        <span class="shrink-0 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ __('admin.content_groups.variant_statuses.'.$variant->status) }}</span>
                    </div>
                    <dl class="mt-5 grid grid-cols-2 gap-4 border-t border-gray-100 pt-5 text-sm">
                        <div>
                            <dt class="text-gray-500">{{ __('admin.content_groups.title_limit') }}</dt>
                            <dd class="mt-1 font-semibold text-gray-900">{{ $template['title_max_chars'] ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">{{ __('admin.content_groups.content_range') }}</dt>
                            <dd class="mt-1 font-semibold text-gray-900">{{ ($template['content_min_chars'] ?? '—').'–'.($template['content_max_chars'] ?? '—') }}</dd>
                        </div>
                    </dl>
                    <div class="mt-5 rounded-md bg-gray-50 p-4 text-sm leading-6 text-gray-600">{{ $template['instructions'] ?? '' }}</div>
                    @if ($variant->title !== '')
                        <div class="mt-5 border-t border-gray-100 pt-5">
                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('admin.content_groups.current_title') }}</div>
                            <div class="mt-2 font-semibold text-gray-900">{{ $variant->title }}</div>
                        </div>
                    @endif
                </article>
            @endforeach
        </div>
    </div>
@endsection
