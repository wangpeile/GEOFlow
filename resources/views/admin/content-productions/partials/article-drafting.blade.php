<section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
    <form method="POST" action="{{ route('admin.content-productions.ownership.update', $production) }}" class="grid grid-cols-1 gap-4 border-b border-gray-200 bg-amber-50 px-6 py-4 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_auto] lg:items-end">
        @csrf
        @method('PATCH')
        <label class="flex flex-col gap-2">
            <span class="text-sm font-semibold text-gray-700">文章分类</span>
            <select name="category_id" required class="rounded-md border-gray-300 bg-white text-sm">
                <option value="">请选择分类</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected((string) old('category_id', data_get($production->context, 'category_id')) === (string) $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="flex flex-col gap-2">
            <span class="text-sm font-semibold text-gray-700">文章作者</span>
            <select name="author_id" required class="rounded-md border-gray-300 bg-white text-sm">
                <option value="">请选择作者</option>
                @foreach ($authors as $author)
                    <option value="{{ $author->id }}" @selected((string) old('author_id', data_get($production->context, 'author_id') ?: ($authors->count() === 1 ? $authors->first()->id : null)) === (string) $author->id)>{{ $author->name }}</option>
                @endforeach
            </select>
        </label>
        <button class="rounded-md border border-amber-300 bg-white px-4 py-2 text-sm font-semibold text-amber-800 hover:bg-amber-100">保存文章归属</button>
    </form>
    <div class="border-b border-gray-200 px-6 py-4">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h2 class="font-semibold text-gray-900">分段写作与文章组装</h2>
                <p class="mt-1 text-sm leading-6 text-gray-500">每个章节独立保存版本；重写单个章节不会改动其他章节。组装后先完成统一质量检查，合格后才固化为主文章。</p>
            </div>
            @if (auth('admin')->user()?->canManageProtectedWorkflows() && config('geoflow.content_production_pipeline_enabled', false))
                <div class="flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('admin.content-productions.article.sections.initialize', $production) }}">
                        @csrf
                        <button class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">建立章节</button>
                    </form>
                    <form method="POST" action="{{ route('admin.content-productions.article.sections.generate-all', $production) }}">
                        @csrf
                        <button class="rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700">生成未完成章节</button>
                    </form>
                    <form method="POST" action="{{ route('admin.content-productions.article.assemble', $production) }}">
                        @csrf
                        <button class="rounded-md bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700">组装文章草稿</button>
                    </form>
                </div>
            @endif
        </div>
    </div>

    <div class="divide-y divide-gray-100">
        @forelse ($latestSections as $section)
            @php
                $statusClasses = match ($section->status) {
                    \App\Enums\ContentSectionStatus::Succeeded => 'bg-emerald-50 text-emerald-700',
                    \App\Enums\ContentSectionStatus::Failed => 'bg-red-50 text-red-700',
                    \App\Enums\ContentSectionStatus::Running => 'bg-blue-50 text-blue-700',
                    default => 'bg-slate-100 text-slate-700',
                };
            @endphp
            <article class="px-6 py-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded border border-gray-200 px-2 py-0.5 text-xs font-bold uppercase text-gray-500">{{ $section->level }}</span>
                            <h3 class="font-semibold text-gray-900">{{ $section->heading }}</h3>
                            <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $statusClasses }}">{{ $section->status->value }}</span>
                            <span class="text-xs text-gray-400">版本 {{ $section->version }}</span>
                        </div>
                        @if ($section->error_message)
                            <p class="mt-2 text-sm text-red-600">{{ $section->error_message }}</p>
                        @endif
                    </div>
                    @if (auth('admin')->user()?->canManageProtectedWorkflows() && config('geoflow.content_production_pipeline_enabled', false))
                        <form method="POST" action="{{ route('admin.content-productions.article.sections.generate', [$production, $section->section_key]) }}">
                            @csrf
                            <button class="whitespace-nowrap text-sm font-semibold text-blue-600 hover:text-blue-800">
                                {{ $section->status === \App\Enums\ContentSectionStatus::Failed ? '重试本章' : '重新生成本章' }}
                            </button>
                        </form>
                    @endif
                </div>

                @if (auth('admin')->user()?->canManageProtectedWorkflows() && config('geoflow.content_production_pipeline_enabled', false))
                    <form method="POST" action="{{ route('admin.content-productions.article.sections.save', [$production, $section->section_key]) }}" class="mt-4">
                        @csrf
                        @method('PUT')
                        <textarea name="content" required maxlength="100000" rows="7" class="block w-full rounded-md border-gray-300 text-sm leading-6 focus:border-blue-500 focus:ring-blue-500" placeholder="在此手工撰写或调整本章节">{{ old('content', $section->content) }}</textarea>
                        <div class="mt-2 flex items-center justify-between gap-3">
                            <p class="text-xs text-gray-400">保存时会创建新版本，已有版本不会被覆盖。</p>
                            <button class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">保存本章</button>
                        </div>
                    </form>
                @elseif ($section->content)
                    <div class="mt-4 whitespace-pre-wrap text-sm leading-7 text-gray-700">{{ $section->content }}</div>
                @endif
            </article>
        @empty
            <div class="px-6 py-10 text-center">
                <p class="text-sm font-semibold text-gray-700">确认大纲后，可建立独立章节并开始写作。</p>
                <p class="mt-1 text-sm text-gray-500">章节失败不会清空已经成功的内容。</p>
            </div>
        @endforelse
    </div>

    @if ($currentArticleVersion)
        <div class="border-t border-gray-200 bg-slate-50 px-6 py-5">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="font-semibold text-gray-900">当前组装版本：{{ $currentArticleVersion->title }}</h3>
                        <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700">版本 {{ $currentArticleVersion->version }}</span>
                    </div>
                    <p class="mt-2 text-sm leading-6 text-gray-600">{{ $currentArticleVersion->summary }}</p>
                    <p class="mt-2 text-xs text-gray-400">Meta 描述：{{ $currentArticleVersion->meta_description }}</p>
                </div>
                @if ($currentArticleVersion->article_id)
                    <a href="{{ route('admin.articles.edit', $currentArticleVersion->article_id) }}" class="whitespace-nowrap rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">打开文章草稿</a>
                @endif
            </div>
            @if (auth('admin')->user()?->canManageProtectedWorkflows() && config('geoflow.content_production_pipeline_enabled', false))
                <form method="POST" action="{{ route('admin.content-productions.article.revisions.store', $production) }}" class="mt-5 border-t border-slate-200 pt-5">
                    @csrf
                    <label for="article-feedback" class="block text-sm font-semibold text-gray-900">修改意见</label>
                    <p class="mt-1 text-xs leading-5 text-gray-500">说明希望修改的事实、表达、结构或受众重点。系统会创建新版本，不会覆盖当前版本，并自动重新质检。</p>
                    <textarea id="article-feedback" name="feedback" rows="4" maxlength="5000" required class="mt-3 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="例如：把第二部分改得更适合中小企业读者，并补充实施前的准备清单。">{{ old('feedback') }}</textarea>
                    @error('feedback')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                    <div class="mt-3 flex justify-end"><button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">按意见创建修订版本</button></div>
                </form>
            @endif
            @if ($production->articleRevisionRequests->isNotEmpty())
                <div class="mt-5 border-t border-slate-200 pt-5">
                    <h4 class="text-sm font-semibold text-gray-900">修改记录</h4>
                    <div class="mt-3 space-y-3">
                        @foreach ($production->articleRevisionRequests->take(5) as $revision)
                            <div class="rounded-md border border-slate-200 bg-white p-3 text-sm">
                                <div class="flex flex-wrap items-center gap-2"><span class="font-medium text-gray-800">版本 {{ $revision->sourceArticleVersion?->version ?? '—' }} → {{ $revision->revisedArticleVersion?->version ?? '处理中' }}</span><span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">{{ $revision->status }}</span></div>
                                <p class="mt-2 whitespace-pre-wrap text-gray-600">{{ $revision->feedback }}</p>
                                @if (filled(data_get($revision->result, 'changes')))<p class="mt-2 text-xs text-gray-500">{{ implode('；', (array) data_get($revision->result, 'changes')) }}</p>@endif
                                @if ($revision->error_message)<p class="mt-2 text-xs text-red-600">{{ $revision->error_message }}</p>@endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @endif
</section>
