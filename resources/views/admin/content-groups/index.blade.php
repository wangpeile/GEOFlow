@extends('admin.layouts.app')

@section('content')
    <div class="px-4 sm:px-0">
        <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">{{ __('admin.content_groups.heading') }}</h1>
                <p class="mt-1 text-sm leading-6 text-gray-600">{{ __('admin.content_groups.subtitle') }}</p>
            </div>
            <a href="{{ route('admin.articles.index') }}" class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                <i data-lucide="arrow-left" class="mr-2 h-4 w-4"></i>
                {{ __('admin.content_groups.back_to_articles') }}
            </a>
        </div>

        <section class="mb-6 rounded-lg border border-blue-100 bg-blue-50/60 p-5">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h2 class="text-base font-semibold text-gray-900">{{ __('admin.content_groups.create_title') }}</h2>
                    <p class="mt-1 text-sm text-gray-600">{{ __('admin.content_groups.create_desc') }}</p>
                </div>
                @if ($availableArticles->isNotEmpty())
                    <form method="POST" action="{{ route('admin.content-groups.store', $availableArticles->first()) }}" class="flex w-full flex-col gap-3 sm:flex-row lg:max-w-2xl" data-content-group-create-form>
                        @csrf
                        <select class="min-w-0 flex-1 rounded-md border-gray-300 bg-white text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" data-content-group-article-select>
                            @foreach ($availableArticles as $article)
                                <option value="{{ route('admin.content-groups.store', $article) }}">{{ $article->title }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="inline-flex shrink-0 items-center justify-center rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-700">
                            <i data-lucide="layers-3" class="mr-2 h-4 w-4"></i>
                            {{ __('admin.content_groups.create_action') }}
                        </button>
                    </form>
                @else
                    <div class="text-sm font-medium text-gray-500">{{ __('admin.content_groups.no_available_articles') }}</div>
                @endif
            </div>
        </section>

        <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('admin.content_groups.group') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('admin.content_groups.task') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('admin.content_groups.progress') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('admin.content_groups.status') }}</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('admin.content_groups.action') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        @forelse ($contentGroups as $contentGroup)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <div class="max-w-xl font-semibold text-gray-900">{{ $contentGroup->name }}</div>
                                    <div class="mt-1 text-xs text-gray-500">{{ $contentGroup->mainArticle?->title }}</div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600">{{ $contentGroup->task?->name ?: '—' }}</td>
                                <td class="px-6 py-4 text-sm text-gray-600">
                                    {{ __('admin.content_groups.ready_count', ['ready' => $contentGroup->ready_variants_count, 'total' => $contentGroup->variants_count]) }}
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ __('admin.content_groups.statuses.'.$contentGroup->status) }}</span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <a href="{{ route('admin.content-groups.show', $contentGroup) }}" class="text-sm font-semibold text-blue-600 hover:text-blue-800">{{ __('admin.content_groups.view') }}</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center">
                                    <div class="text-sm font-semibold text-gray-900">{{ __('admin.content_groups.empty_title') }}</div>
                                    <p class="mt-2 text-sm text-gray-500">{{ __('admin.content_groups.empty_desc') }}</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($contentGroups->hasPages())
                <div class="border-t border-gray-200 px-6 py-4">{{ $contentGroups->links() }}</div>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/admin-content-groups.js') }}"></script>
@endpush
