@extends('admin.layouts.app')

@section('content')
    <div class="mx-auto flex max-w-3xl flex-col gap-6 px-4 sm:px-0">
        <div>
            <a href="{{ route('admin.content-productions.index') }}" class="text-sm font-semibold text-blue-600 hover:text-blue-800">← 返回内容生产</a>
            <h1 class="mt-3 text-2xl font-bold text-gray-900">新建文章</h1>
            <p class="mt-1 text-sm leading-6 text-gray-600">先建立一张文章工作单，再按步骤补充资料、确认方向、生成主文章并准备发布。</p>
        </div>

        <form method="POST" action="{{ route('admin.content-productions.store') }}" class="flex flex-col gap-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            @csrf
            <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}">

            <label class="flex flex-col gap-2">
                <span class="text-sm font-semibold text-gray-700">主题或问题 <span class="text-red-500">*</span></span>
                <textarea name="topic" rows="4" required maxlength="500" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="例如：企业如何选择视频会议系统">{{ old('topic') }}</textarea>
            </label>

            <label class="flex flex-col gap-2">
                <span class="text-sm font-semibold text-gray-700">文章工作单名称</span>
                <input name="name" maxlength="255" value="{{ old('name') }}" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="仅用于内部识别；留空时使用主题作为名称">
            </label>

            <fieldset class="rounded-lg border border-violet-200 bg-violet-50/50 p-4">
                <legend class="px-1 text-sm font-semibold text-violet-900">内容专题（可选）</legend>
                <p class="mt-1 text-xs leading-5 text-violet-700">临时单篇文章无需选择专题；选择后仅复制专题当前资料与规则作为本工作单的快照。</p>
                <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <label class="flex flex-col gap-2"><span class="text-sm font-medium text-gray-700">所属专题</span><select name="content_topic_id" class="rounded-md border-gray-300 text-sm"><option value="">不属于专题</option>@foreach ($contentTopics as $contentTopic)<option value="{{ $contentTopic->id }}" @selected((string) old('content_topic_id', $selectedTopic?->id) === (string) $contentTopic->id)>{{ $contentTopic->name }}</option>@endforeach</select></label>
                    @if ($selectedIdea)
                        <input type="hidden" name="content_topic_idea_id" value="{{ $selectedIdea->id }}">
                        <div class="flex flex-col gap-2"><span class="text-sm font-medium text-gray-700">使用选题池条目</span><div class="rounded-md border border-violet-200 bg-white px-3 py-2 text-sm text-gray-800">{{ $selectedIdea->topic }}</div></div>
                    @endif
                </div>
                <div class="mt-3"><a href="{{ route('admin.content-topics.create') }}" class="text-xs font-semibold text-violet-700 hover:text-violet-900">新建内容专题 →</a></div>
            </fieldset>

            <label class="flex flex-col gap-2">
                <span class="text-sm font-semibold text-gray-700">写作规则</span>
                <select name="writing_rule_id" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="">本次手动配置</option>
                    @foreach ($writingRules as $writingRule)
                        <option value="{{ $writingRule->id }}" @selected((string) old('writing_rule_id') === (string) $writingRule->id)>
                            {{ $writingRule->name }} · {{ $writingRule->articleType?->name ?: '未分类' }} · v{{ $writingRule->current_version }}
                        </option>
                    @endforeach
                </select>
                <span class="text-xs text-gray-500">创建时会保存当前规则快照，后续修改规则不会改变本项目。</span>
                <a href="{{ route('admin.writing-rules.index') }}" class="text-xs font-semibold text-blue-600 hover:text-blue-800">管理写作规则</a>
            </label>

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <label class="flex flex-col gap-2">
                    <span class="text-sm font-semibold text-gray-700">创作模式</span>
                    <input type="hidden" name="mode" value="guided">
                    <div class="rounded-md border border-blue-200 bg-blue-50 px-3 py-2 text-sm font-semibold text-blue-800">文章工作台</div>
                </label>
                <label class="flex flex-col gap-2">
                    <span class="text-sm font-semibold text-gray-700">文章语言</span>
                    <select name="language" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="zh_CN">中文（简体）</option>
                    </select>
                </label>
            </div>

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <label class="flex flex-col gap-2">
                    <span class="text-sm font-semibold text-gray-700">文章分类 <span class="text-red-500">*</span></span>
                    <select name="category_id" required class="rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">请选择分类</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected((string) old('category_id') === (string) $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="flex flex-col gap-2">
                    <span class="text-sm font-semibold text-gray-700">文章作者 <span class="text-red-500">*</span></span>
                    <select name="author_id" required class="rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">请选择作者</option>
                        @foreach ($authors as $author)
                            <option value="{{ $author->id }}" @selected((string) old('author_id', $authors->count() === 1 ? $authors->first()->id : null) === (string) $author->id)>{{ $author->name }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            <fieldset class="flex flex-col gap-3">
                <legend class="text-sm font-semibold text-gray-700">目标平台</legend>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    @foreach (['wordpress' => 'WordPress', 'baijiahao' => '百家号', 'qq' => '腾讯', 'netease' => '网易', 'sohu' => '搜狐', 'toutiao' => '头条号', 'zhihu' => '知乎', 'wechat' => '公众号'] as $value => $label)
                        <label class="flex items-center gap-2 rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-700">
                            <input type="checkbox" name="target_platforms[]" value="{{ $value }}" @checked(in_array($value, old('target_platforms', ['wordpress']), true)) class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
                <div class="grid grid-cols-1 gap-3 pt-2 lg:grid-cols-2">
                    @foreach ($platformRequirements as $platform)
                        <article class="rounded-md border border-gray-200 bg-gray-50 p-4">
                            <div class="flex items-center justify-between gap-3">
                                <h3 class="text-sm font-semibold text-gray-900">{{ $platform['label'] }}</h3>
                                <span class="rounded-full bg-white px-2 py-1 text-xs text-gray-500">{{ $platform['publishing_mode'] === 'automatic' ? '可自动发布' : '人工发布' }}</span>
                            </div>
                            <dl class="mt-3 space-y-2 text-xs leading-5 text-gray-600">
                                <div><dt class="inline font-semibold text-gray-700">身份：</dt><dd class="inline">{{ $platform['content_identity'] }}</dd></div>
                                <div><dt class="inline font-semibold text-gray-700">视角：</dt><dd class="inline">{{ $platform['perspective'] }}</dd></div>
                                <div><dt class="inline font-semibold text-gray-700">链接：</dt><dd class="inline">{{ $platform['link_policy'] }}</dd></div>
                                <div><dt class="inline font-semibold text-gray-700">写法：</dt><dd class="inline">{{ $platform['instructions'] }}</dd></div>
                            </dl>
                        </article>
                    @endforeach
                </div>
            </fieldset>

            <div class="flex justify-end gap-3 border-t border-gray-100 pt-5">
                <a href="{{ route('admin.content-productions.index') }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">取消</a>
                <button class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-700">创建文章工作单</button>
            </div>
        </form>
    </div>
@endsection
