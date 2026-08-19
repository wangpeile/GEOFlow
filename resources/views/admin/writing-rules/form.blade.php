@extends('admin.layouts.app')

@php
    $editing = $rule !== null;
    $value = fn (string $key, mixed $default = null) => old($key, $settings[$key] ?? $default);
    $selectedKnowledgeBases = array_map('strval', old('knowledge_base_ids', $settings['knowledge_base_ids'] ?? []));
    $selectedSensitiveWords = array_map('strval', old('sensitive_word_ids', $settings['sensitive_word_ids'] ?? []));
    $internalLinks = old('internal_links', $settings['internal_links'] ?? []);
    $internalLinks = is_array($internalLinks)
        ? collect($internalLinks)->map(fn (array $link) => ($link['anchor'] ?? '').'|'.($link['url'] ?? ''))->implode("\n")
        : (string) $internalLinks;
@endphp

@section('content')
    <div class="mx-auto flex max-w-5xl flex-col gap-6 px-4 sm:px-0">
        <div>
            <a href="{{ route('admin.writing-rules.index') }}" class="text-sm font-semibold text-blue-600 hover:text-blue-800">← 返回写作规则</a>
            <h1 class="mt-3 text-2xl font-bold text-gray-900">{{ $editing ? '编辑写作规则' : '新建写作规则' }}</h1>
            <p class="mt-1 text-sm text-gray-600">界面使用常规字段保存，系统会在底层生成结构化配置和不可修改的版本快照。</p>
        </div>

        <form method="POST" action="{{ $editing ? route('admin.writing-rules.update', $rule) : route('admin.writing-rules.store') }}" class="flex flex-col gap-6">
            @csrf
            @if ($editing) @method('PUT') @endif

            <section class="grid grid-cols-1 gap-5 rounded-lg border border-gray-200 bg-white p-6 shadow-sm sm:grid-cols-2">
                <h2 class="sm:col-span-2 text-lg font-semibold text-gray-900">基础信息</h2>
                <label class="flex flex-col gap-2"><span class="text-sm font-semibold text-gray-700">规则名称 *</span><input name="name" required maxlength="255" value="{{ old('name', $rule?->name) }}" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"></label>
                <label class="flex flex-col gap-2"><span class="text-sm font-semibold text-gray-700">文章类型 *</span><select name="article_type_id" required class="rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"><option value="">请选择</option>@foreach ($articleTypes as $type)<option value="{{ $type->id }}" @selected((string) old('article_type_id', $rule?->article_type_id) === (string) $type->id)>{{ $type->name }}</option>@endforeach</select><a href="{{ route('admin.article-types.index') }}" class="text-xs font-semibold text-blue-600">管理文章类型</a></label>
                <label class="sm:col-span-2 flex flex-col gap-2"><span class="text-sm font-semibold text-gray-700">规则说明</span><textarea name="description" rows="3" maxlength="2000" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('description', $rule?->description) }}</textarea></label>
                <label class="flex items-center gap-2"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', $rule?->is_active ?? true)) class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"><span class="text-sm font-semibold text-gray-700">启用这条规则</span></label>
            </section>

            <section class="grid grid-cols-1 gap-5 rounded-lg border border-gray-200 bg-white p-6 shadow-sm sm:grid-cols-2 lg:grid-cols-3">
                <h2 class="sm:col-span-2 lg:col-span-3 text-lg font-semibold text-gray-900">内容要求</h2>
                <label class="flex flex-col gap-2"><span class="text-sm font-semibold text-gray-700">语言</span><select name="language" class="rounded-md border-gray-300 text-sm"><option value="zh_CN">中文（简体）</option></select></label>
                <label class="flex flex-col gap-2"><span class="text-sm font-semibold text-gray-700">目标国家/地区</span><select name="country" class="rounded-md border-gray-300 text-sm"><option value="CN">中国</option></select></label>
                <label class="flex flex-col gap-2"><span class="text-sm font-semibold text-gray-700">文章语气</span><select name="tone" class="rounded-md border-gray-300 text-sm">@foreach (['professional'=>'专业','neutral'=>'中立','friendly'=>'亲切','authoritative'=>'权威','conversational'=>'对话式'] as $key=>$label)<option value="{{ $key }}" @selected($value('tone','professional')===$key)>{{ $label }}</option>@endforeach</select></label>
                <label class="flex flex-col gap-2"><span class="text-sm font-semibold text-gray-700">人称角度</span><select name="perspective" class="rounded-md border-gray-300 text-sm">@foreach (['auto'=>'自动','first_singular'=>'第一人称单数','first_plural'=>'第一人称复数','second'=>'第二人称','third'=>'第三人称'] as $key=>$label)<option value="{{ $key }}" @selected($value('perspective','auto')===$key)>{{ $label }}</option>@endforeach</select></label>
                <label class="flex flex-col gap-2"><span class="text-sm font-semibold text-gray-700">发布者身份</span><select name="publisher_identity" class="rounded-md border-gray-300 text-sm">@foreach (['official_brand'=>'厂商/品牌官方网站','independent_editorial'=>'独立第三方编辑'] as $key=>$label)<option value="{{ $key }}" @selected($value('publisher_identity','official_brand')===$key)>{{ $label }}</option>@endforeach</select><span class="text-xs text-gray-500">WordPress 官网首发建议选择厂商/品牌官方网站。</span></label>
                <label class="flex flex-col gap-2"><span class="text-sm font-semibold text-gray-700">品牌或厂商名称</span><input name="brand_name" maxlength="255" value="{{ $value('brand_name') }}" class="rounded-md border-gray-300 text-sm" placeholder="例如：红鲸科技"></label>
                <label class="flex flex-col gap-2"><span class="text-sm font-semibold text-gray-700">官方网站</span><input type="url" name="official_site_url" maxlength="2048" value="{{ $value('official_site_url') }}" class="rounded-md border-gray-300 text-sm" placeholder="https://www.example.com"></label>
                <label class="flex flex-col gap-2"><span class="text-sm font-semibold text-gray-700">正式程度</span><select name="formality" class="rounded-md border-gray-300 text-sm">@foreach (['auto'=>'自动','formal'=>'正式','informal'=>'非正式'] as $key=>$label)<option value="{{ $key }}" @selected($value('formality','auto')===$key)>{{ $label }}</option>@endforeach</select></label>
                <label class="flex flex-col gap-2"><span class="text-sm font-semibold text-gray-700">创意程度（0-100）</span><input type="number" name="creativity" min="0" max="100" value="{{ $value('creativity',30) }}" class="rounded-md border-gray-300 text-sm"></label>
                <label class="flex flex-col gap-2"><span class="text-sm font-semibold text-gray-700">最少字数</span><input type="number" name="min_words" min="300" max="10000" value="{{ $value('min_words',1000) }}" class="rounded-md border-gray-300 text-sm"></label>
                <label class="flex flex-col gap-2"><span class="text-sm font-semibold text-gray-700">最多字数</span><input type="number" name="max_words" min="300" max="10000" value="{{ $value('max_words',2000) }}" class="rounded-md border-gray-300 text-sm"></label>
                <div class="hidden lg:block"></div>
                <label class="flex flex-col gap-2"><span class="text-sm font-semibold text-gray-700">最少小标题</span><input type="number" name="min_headings" min="2" max="20" value="{{ $value('min_headings',5) }}" class="rounded-md border-gray-300 text-sm"></label>
                <label class="flex flex-col gap-2"><span class="text-sm font-semibold text-gray-700">最多小标题</span><input type="number" name="max_headings" min="2" max="20" value="{{ $value('max_headings',8) }}" class="rounded-md border-gray-300 text-sm"></label>
            </section>

            <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-gray-900">结构与增强功能</h2>
                <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach (['include_citations'=>'包含引用','include_internal_links'=>'包含内部链接','include_external_links'=>'包含外部链接','include_faq'=>'包含常见问题','include_cta'=>'包含行动号召'] as $key=>$label)
                        <label class="flex items-center gap-2 rounded-md border border-gray-200 px-3 py-3"><input type="hidden" name="{{ $key }}" value="0"><input type="checkbox" name="{{ $key }}" value="1" @checked((bool) $value($key, in_array($key, ['include_citations','include_external_links','include_faq'], true))) class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"><span class="text-sm font-semibold text-gray-700">{{ $label }}</span></label>
                    @endforeach
                </div>
                <label class="mt-5 flex flex-col gap-2"><span class="text-sm font-semibold text-gray-700">行动号召内容</span><textarea name="cta_text" rows="2" maxlength="500" class="rounded-md border-gray-300 text-sm">{{ $value('cta_text') }}</textarea></label>
                <label class="mt-5 flex flex-col gap-2"><span class="text-sm font-semibold text-gray-700">官网内部链接</span><textarea name="internal_links" rows="5" maxlength="10000" class="rounded-md border-gray-300 text-sm" placeholder="每行一条：锚文本|https://www.example.com/page">{{ $internalLinks }}</textarea><span class="text-xs leading-5 text-gray-500">正文只会从这里选择与章节真正相关的链接，不会编造 URL；关闭“包含内部链接”后不会使用。</span></label>
            </section>

            <section class="grid grid-cols-1 gap-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm lg:grid-cols-2">
                <div><h2 class="text-lg font-semibold text-gray-900">知识库</h2><p class="mt-1 text-xs text-gray-500">最多选择 5 个，用于事实和业务资料检索。</p><div class="mt-4 max-h-56 space-y-2 overflow-y-auto rounded-md border border-gray-200 p-3">@forelse ($knowledgeBases as $base)<label class="flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" name="knowledge_base_ids[]" value="{{ $base->id }}" @checked(in_array((string) $base->id, $selectedKnowledgeBases, true)) class="rounded border-gray-300 text-blue-600">{{ $base->name }}</label>@empty<p class="text-sm text-gray-500">暂无知识库。</p>@endforelse</div></div>
                <div><h2 class="text-lg font-semibold text-gray-900">敏感词库</h2><p class="mt-1 text-xs text-gray-500">命中后可在质量检查阶段提示或阻止发布。</p><div class="mt-4 max-h-56 space-y-2 overflow-y-auto rounded-md border border-gray-200 p-3">@forelse ($sensitiveWords as $word)<label class="flex items-center gap-2 text-sm text-gray-700"><input type="checkbox" name="sensitive_word_ids[]" value="{{ $word->id }}" @checked(in_array((string) $word->id, $selectedSensitiveWords, true)) class="rounded border-gray-300 text-blue-600">{{ $word->word }} <span class="text-xs text-gray-400">{{ $word->severity }}</span></label>@empty<p class="text-sm text-gray-500">暂无启用的敏感词。</p>@endforelse</div></div>
                <label class="flex flex-col gap-2"><span class="text-sm font-semibold text-gray-700">品牌资料</span><textarea name="brand_profile" rows="5" maxlength="5000" class="rounded-md border-gray-300 text-sm" placeholder="品牌名称、定位、核心优势、禁止表述等">{{ $value('brand_profile') }}</textarea></label>
                <label class="flex flex-col gap-2"><span class="text-sm font-semibold text-gray-700">补充写作要求</span><textarea name="instructions" rows="5" maxlength="5000" class="rounded-md border-gray-300 text-sm" placeholder="例如：每个章节先给出直接答案，再展开说明">{{ $value('instructions') }}</textarea></label>
            </section>

            @if ($editing)<label class="flex flex-col gap-2 rounded-lg border border-gray-200 bg-white p-6 shadow-sm"><span class="text-sm font-semibold text-gray-700">版本变更说明</span><input name="change_note" maxlength="500" value="{{ old('change_note') }}" class="rounded-md border-gray-300 text-sm" placeholder="说明本次修改内容"></label>@endif
            <div class="flex justify-end gap-3"><a href="{{ route('admin.writing-rules.index') }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">取消</a><button class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">{{ $editing ? '保存为新版本' : '创建规则' }}</button></div>
        </form>
    </div>
@endsection
