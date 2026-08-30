@extends('admin.layouts.app')

@section('content')
    @php
        $assets = [
            ['知识库', '将文档、网页和可复用事实沉淀为创作依据。', 'database', route('admin.knowledge-bases.index')],
            ['URL 资料', '导入外部网页，作为单篇工作单的补充资料。', 'link', route('admin.url-import')],
            ['关键词库', '管理选题、关键词组合与专题输入。', 'tags', route('admin.keyword-libraries.index')],
            ['图片与素材', '管理可复用的图片和媒体资产。', 'image', route('admin.image-libraries.index')],
            ['写作规则', '沉淀品牌语气、篇幅、结构和内容规范。', 'file-cog', route('admin.writing-rules.index')],
            ['文章类型', '统一博客、教程、测评、清单等写法。', 'library', route('admin.article-types.index')],
        ];
    @endphp
    <div class="mx-auto flex max-w-6xl flex-col gap-6 px-4 sm:px-0">
        <section class="rounded-2xl border border-violet-100 bg-gradient-to-br from-violet-50 via-white to-blue-50 p-6 sm:p-8">
            <p class="text-sm font-semibold text-violet-700">内容资产</p>
            <h1 class="mt-2 text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl">把可复用的知识、规则和素材放在生产之前</h1>
            <p class="mt-3 max-w-3xl text-sm leading-6 text-gray-600">内容资产不直接等于文章。它们是每张文章工作单可按需引用的依据；AI 研究仍可在创作时从公开网络补充最新信息。</p>
        </section>
        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($assets as [$title, $description, $icon, $url])
                <a href="{{ $url }}" class="group rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-violet-300 hover:bg-violet-50/40">
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-violet-50 text-violet-700 group-hover:bg-violet-100"><i data-lucide="{{ $icon }}" class="h-5 w-5"></i></span>
                    <h2 class="mt-4 font-semibold text-gray-900">{{ $title }}</h2>
                    <p class="mt-2 text-sm leading-6 text-gray-500">{{ $description }}</p>
                    <span class="mt-4 inline-flex text-sm font-semibold text-violet-700">进入管理 →</span>
                </a>
            @endforeach
        </section>
    </div>
@endsection
