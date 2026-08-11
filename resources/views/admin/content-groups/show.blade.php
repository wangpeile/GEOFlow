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

        @if (auth('admin')->user()?->canManageProtectedWorkflows() && config('geoflow.content_production_pipeline_enabled', false))
            <form method="POST" action="{{ route('admin.content-groups.variants.generate', $contentGroup) }}" class="mb-8 rounded-xl border border-violet-200 bg-violet-50/50 p-5 shadow-sm">
                @csrf
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">生成平台版本</h2>
                        <p class="mt-1 text-sm leading-6 text-gray-600">每次选择一个平台生成独立中文版本。再次生成会新增历史版本，不覆盖其他平台。</p>
                        <div class="mt-4 flex flex-wrap gap-3">
                            @foreach ($contentGroup->variants->where('platform', '!=', 'wordpress') as $variant)
                                <label class="inline-flex items-center gap-2 rounded-md border border-violet-200 bg-white px-3 py-2 text-sm text-gray-700">
                                    <input type="radio" name="platforms[]" value="{{ $variant->platform }}" class="border-gray-300 text-violet-600 focus:ring-violet-500">
                                    <span>{{ $platforms[$variant->platform]['label'] ?? $variant->platform }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <button type="submit" class="inline-flex shrink-0 items-center justify-center rounded-md bg-violet-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-violet-700">
                        <i data-lucide="sparkles" class="mr-2 h-4 w-4"></i>
                        生成所选平台
                    </button>
                </div>
            </form>
        @endif

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
                            @if ($variant->excerpt)
                                <p class="mt-2 text-sm leading-6 text-gray-600">{{ $variant->excerpt }}</p>
                            @endif
                            @if ($variant->tags)
                                <div class="mt-3 flex flex-wrap gap-2">
                                    @foreach ($variant->tags as $tag)
                                        <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs text-gray-600">{{ $tag }}</span>
                                    @endforeach
                                </div>
                            @endif
                            <details class="mt-4 rounded-md border border-gray-200 bg-white p-3">
                                <summary class="cursor-pointer text-sm font-semibold text-gray-700">查看当前正文</summary>
                                <div class="mt-3 whitespace-pre-wrap text-sm leading-7 text-gray-700">{{ $variant->content }}</div>
                            </details>
                        </div>
                    @endif
                    @if ($variant->fact_check)
                        <div class="mt-4 rounded-md px-3 py-2 text-sm {{ ($variant->fact_check['passed'] ?? false) ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-800' }}">
                            {{ ($variant->fact_check['passed'] ?? false) ? '硬事实差异检查通过' : '发现源文章之外的数字、日期或链接，请人工核对' }}
                            @if (!empty($variant->fact_check['extra_hard_facts']))
                                <div class="mt-1 text-xs">{{ implode('、', $variant->fact_check['extra_hard_facts']) }}</div>
                            @endif
                        </div>
                    @endif
                    @if ($variant->failure_message)
                        <div class="mt-4 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{{ $variant->failure_message }}</div>
                    @endif
                    @if ($variant->platform !== 'wordpress' && auth('admin')->user()?->canManageProtectedWorkflows() && config('geoflow.content_production_pipeline_enabled', false))
                        <form method="POST" action="{{ route('admin.content-groups.variants.regenerate', [$contentGroup, $variant]) }}" class="mt-5">
                            @csrf
                            <button type="submit" class="w-full rounded-md border border-violet-300 bg-white px-4 py-2 text-sm font-semibold text-violet-700 hover:bg-violet-50" @disabled($variant->status === \App\Models\ContentVariant::STATUS_GENERATING)>
                                {{ $variant->title === '' ? '生成此平台版本' : '重新生成并保留历史版本' }}
                            </button>
                        </form>
                    @endif
                    @if ($variant->versions->isNotEmpty())
                        <details class="mt-4 border-t border-gray-100 pt-4">
                            <summary class="cursor-pointer text-sm font-semibold text-gray-600">版本历史（{{ $variant->versions->count() }}）</summary>
                            <ol class="mt-3 flex flex-col gap-2">
                                @foreach ($variant->versions as $version)
                                    <li class="rounded-md bg-gray-50 px-3 py-2 text-xs text-gray-600">
                                        V{{ $version->version }} · {{ $version->created_at?->format('Y-m-d H:i') }} · {{ $version->creator?->username ?: '系统' }}
                                        <div class="mt-1 font-medium text-gray-800">{{ $version->title }}</div>
                                    </li>
                                @endforeach
                            </ol>
                        </details>
                    @endif
                </article>
            @endforeach
        </div>
    </div>
@endsection
