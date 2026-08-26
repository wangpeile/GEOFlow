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

        @php($exportableVariants = $contentGroup->variants->filter(fn ($item) => $item->platform !== 'wordpress' && $item->review_status === \App\Models\ContentVariant::REVIEW_APPROVED && $item->status === \App\Models\ContentVariant::STATUS_READY))
        <form method="POST" action="{{ route('admin.content-groups.variants.export', $contentGroup) }}" class="mb-8 rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            @csrf
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h2 class="text-base font-semibold text-gray-900">批量导出平台稿</h2>
                    <p class="mt-1 text-sm text-gray-600">仅可选择审核通过的版本，导出包包含 Markdown、元数据和固定版本号。</p>
                    <div class="mt-3 flex flex-wrap gap-3">
                        @forelse ($exportableVariants as $variant)
                            <label class="inline-flex items-center gap-2 rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-700">
                                <input type="checkbox" name="variant_ids[]" value="{{ $variant->id }}" class="rounded border-gray-300 text-violet-600 focus:ring-violet-500">
                                {{ $platforms[$variant->platform]['label'] ?? $variant->platform }} V{{ $variant->version }}
                            </label>
                        @empty
                            <span class="text-sm text-gray-500">暂无可导出的审核通过版本。</span>
                        @endforelse
                    </div>
                </div>
                <button type="submit" @disabled($exportableVariants->isEmpty()) class="rounded-md bg-gray-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-gray-700 disabled:cursor-not-allowed disabled:opacity-40">导出所选平台稿</button>
            </div>
        </form>

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
                                @if(isset($staleVariantReasons[$variant->id]))<span class="shrink-0 rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800">主文章已更新</span>@endif
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
                        <div><dt class="text-gray-500">发布身份</dt><dd class="mt-1 font-semibold text-gray-900">{{ $template['content_identity'] ?? '—' }}</dd></div>
                        <div><dt class="text-gray-500">叙述视角</dt><dd class="mt-1 font-semibold text-gray-900">{{ $template['perspective'] ?? '—' }}</dd></div>
                    </dl>
                    <div class="mt-5 rounded-md bg-gray-50 p-4 text-sm leading-6 text-gray-600">{{ $template['instructions'] ?? '' }}</div>
                    @if (!empty($template['link_policy']))
                        <div class="mt-3 rounded-md border border-blue-100 bg-blue-50 p-3 text-xs leading-5 text-blue-800"><span class="font-semibold">链接要求：</span>{{ $template['link_policy'] }}</div>
                    @endif
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
                    @if ($variant->quality_check)
                        <div class="mt-4 rounded-md px-3 py-2 text-sm {{ ($variant->quality_check['passed'] ?? false) ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700' }}">
                            <div class="font-semibold">{{ ($variant->quality_check['passed'] ?? false) ? '质量检查通过' : '质量检查存在阻断项' }}</div>
                            @foreach (($variant->quality_check['blockers'] ?? []) as $blocker)
                                <div class="mt-1 text-xs">• {{ $blocker }}</div>
                            @endforeach
                            @foreach (($variant->quality_check['warnings'] ?? []) as $warning)
                                <div class="mt-1 text-xs text-amber-700">• {{ $warning }}</div>
                            @endforeach
                        </div>
                    @endif
                    @if ($variant->failure_message)
                        <div class="mt-4 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{{ $variant->failure_message }}</div>
                    @endif
                    @if(isset($staleVariantReasons[$variant->id]))
                        <div class="mt-4 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-800">{{ $staleVariantReasons[$variant->id] }}</div>
                    @endif
                    @if ($variant->platform === 'wordpress')
                        <div class="mt-5 rounded-lg border border-violet-200 bg-violet-50/40 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="text-sm font-semibold text-gray-900">发布到 WordPress</h3>
                                    <p class="mt-1 text-xs leading-5 text-gray-600">发布会固定当前内容版本；再次发布将更新同一篇远端文章。</p>
                                </div>
                                @if ($variant->latestPublication)
                                    <span class="rounded-full bg-white px-2.5 py-1 text-xs font-semibold text-gray-700">{{ $variant->latestPublication->status }}</span>
                                @endif
                            </div>
                            @foreach ($wordpressPreflight['blockers'] as $blocker)
                                <div class="mt-3 rounded-md bg-red-50 px-3 py-2 text-xs text-red-700">阻断：{{ $blocker }}</div>
                            @endforeach
                            @foreach ($wordpressPreflight['warnings'] as $warning)
                                <div class="mt-3 rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-800">提示：{{ $warning }}</div>
                            @endforeach
                            @if ($variant->latestPublication)
                                <dl class="mt-4 grid grid-cols-2 gap-3 text-xs">
                                    <div><dt class="text-gray-500">发布版本</dt><dd class="mt-1 font-semibold text-gray-800">V{{ $variant->latestPublication->published_version ?: '—' }}</dd></div>
                                    <div><dt class="text-gray-500">发布方式</dt><dd class="mt-1 font-semibold text-gray-800">{{ $variant->latestPublication->publication_mode ?: '—' }}</dd></div>
                                </dl>
                                @if ($variant->latestPublication->remote_url)
                                    <a href="{{ $variant->latestPublication->remote_url }}" target="_blank" rel="noopener noreferrer" class="mt-3 inline-flex text-xs font-semibold text-violet-700 hover:text-violet-900">查看 WordPress 文章</a>
                                @endif
                                @if ($variant->latestPublication->last_error_message)
                                    <div class="mt-3 rounded-md bg-red-50 px-3 py-2 text-xs text-red-700">发布失败：{{ $variant->latestPublication->last_error_message }}</div>
                                    <form method="POST" action="{{ route('admin.content-groups.variants.wordpress.retry', [$contentGroup, $variant, $variant->latestPublication]) }}" class="mt-3">
                                        @csrf
                                        <button class="w-full rounded-md border border-red-300 bg-white px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">重试本次发布</button>
                                    </form>
                                @endif
                            @endif
                            <form method="POST" action="{{ route('admin.content-groups.variants.wordpress.publish', [$contentGroup, $variant]) }}" class="mt-4 space-y-3">
                                @csrf
                                <label class="block text-xs font-semibold text-gray-700">WordPress 站点
                                    <select name="distribution_channel_id" required class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-violet-500 focus:ring-violet-500">
                                        <option value="">请选择站点</option>
                                        @foreach ($wordpressChannels as $channel)
                                            <option value="{{ $channel->id }}">{{ $channel->name }}（{{ $channel->domain }}）</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="block text-xs font-semibold text-gray-700">发布方式
                                    <select name="publication_mode" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-violet-500 focus:ring-violet-500">
                                        <option value="draft">保存为草稿</option>
                                        <option value="immediate">立即发布</option>
                                        <option value="scheduled">定时发布</option>
                                    </select>
                                </label>
                                <label class="block text-xs font-semibold text-gray-700">定时时间（仅定时发布填写）
                                    <input type="datetime-local" name="scheduled_for" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-violet-500 focus:ring-violet-500">
                                </label>
                                <button @disabled($wordpressChannels->isEmpty() || $wordpressPreflight['blockers'] !== []) class="w-full rounded-md bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-700 disabled:cursor-not-allowed disabled:opacity-40">提交 WordPress 发布任务</button>
                            </form>
                        </div>
                    @endif
                    @if ($variant->platform !== 'wordpress' && auth('admin')->user()?->canManageProtectedWorkflows() && config('geoflow.content_production_pipeline_enabled', false))
                        <a href="{{ route('admin.content-groups.variants.edit', [$contentGroup, $variant]) }}" class="mt-5 block w-full rounded-md border border-gray-300 bg-white px-4 py-2 text-center text-sm font-semibold text-gray-700 hover:bg-gray-50">人工编辑并保存新版本</a>
                        <form method="POST" action="{{ route('admin.content-groups.variants.regenerate', [$contentGroup, $variant]) }}" class="mt-5">
                            @csrf
                            <button type="submit" class="w-full rounded-md border border-violet-300 bg-white px-4 py-2 text-sm font-semibold text-violet-700 hover:bg-violet-50" @disabled($variant->status === \App\Models\ContentVariant::STATUS_GENERATING)>
                                {{ $variant->title === '' ? '生成此平台版本' : '重新生成并保留历史版本' }}
                            </button>
                        </form>
                        @if ($variant->title !== '')
                            <div class="mt-4 rounded-md border border-gray-200 p-4">
                                <div class="text-sm font-semibold text-gray-800">人工审核</div>
                                <p class="mt-1 text-xs text-gray-500">当前：{{ $variant->review_status }}@if($variant->reviewer) · {{ $variant->reviewer->username }}@endif</p>
                                <form method="POST" action="{{ route('admin.content-groups.variants.review', [$contentGroup, $variant]) }}" class="mt-3 space-y-3">
                                    @csrf
                                    <input type="hidden" name="current_version" value="{{ $variant->version }}">
                                    <textarea name="note" rows="2" maxlength="2000" placeholder="审核意见；退回时必填" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-violet-500 focus:ring-violet-500"></textarea>
                                    <div class="grid grid-cols-2 gap-3">
                                        <button name="decision" value="approved" class="rounded-md bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700">审核通过</button>
                                        <button name="decision" value="rejected" class="rounded-md border border-red-300 bg-white px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">退回修改</button>
                                    </div>
                                </form>
                            </div>
                        @endif
                    @endif
                    @if ($variant->versions->isNotEmpty())
                        <details class="mt-4 border-t border-gray-100 pt-4">
                            <summary class="cursor-pointer text-sm font-semibold text-gray-600">版本历史（{{ $variant->versions->count() }}）</summary>
                            <ol class="mt-3 flex flex-col gap-2">
                                @foreach ($variant->versions as $version)
                                    <li class="rounded-md bg-gray-50 px-3 py-2 text-xs text-gray-600">
                                        V{{ $version->version }} · {{ $version->change_type === 'manual_edit' ? '人工编辑' : 'AI 生成' }} · {{ $version->created_at?->format('Y-m-d H:i') }} · {{ $version->creator?->username ?: '系统' }}
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
