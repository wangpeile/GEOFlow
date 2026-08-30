@extends('admin.layouts.app')

@section('content')
    <div class="mx-auto max-w-5xl px-4 sm:px-0">
        <div class="mb-8"><a href="{{ route('admin.content-platform-specifications.index') }}" class="text-sm font-semibold text-gray-500 hover:text-gray-700">← 返回平台发布规则</a><h1 class="mt-3 text-2xl font-bold text-gray-900">维护 {{ $rules['label'] ?? $platform }} 规则</h1><p class="mt-2 text-sm text-gray-600">请根据账号后台、平台公开指南和实际审核反馈维护。保存后只用于以后新生成的平台稿。</p></div>
        <form method="POST" action="{{ route('admin.content-platform-specifications.update', $platform) }}" class="space-y-6 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            @csrf @method('PUT')
            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                <label class="block text-sm font-semibold text-gray-700">展示名称<input name="label" value="{{ old('label', $specification?->label ?? $rules['label'] ?? '') }}" required class="mt-2 block w-full rounded-md border-gray-300 shadow-sm focus:border-violet-500 focus:ring-violet-500"></label>
                <label class="block text-sm font-semibold text-gray-700">规则版本<input name="version" value="{{ old('version', $specification?->version ?? $rules['template_version'] ?? '1.0') }}" required class="mt-2 block w-full rounded-md border-gray-300 shadow-sm focus:border-violet-500 focus:ring-violet-500"></label>
                <label class="block text-sm font-semibold text-gray-700">状态<select name="status" class="mt-2 block w-full rounded-md border-gray-300 shadow-sm focus:border-violet-500 focus:ring-violet-500"><option value="active" @selected(old('status', $specification?->status ?? 'active') === 'active')>可用</option><option value="review_needed" @selected(old('status', $specification?->status) === 'review_needed')>待复核</option></select></label>
                <label class="block text-sm font-semibold text-gray-700">复核日期<input type="date" name="next_review_at" value="{{ old('next_review_at', $specification?->next_review_at?->format('Y-m-d')) }}" class="mt-2 block w-full rounded-md border-gray-300 shadow-sm focus:border-violet-500 focus:ring-violet-500"></label>
            </div>
            <label class="block text-sm font-semibold text-gray-700">规则来源链接（可选）<input type="url" name="source_url" value="{{ old('source_url', $specification?->source_url) }}" class="mt-2 block w-full rounded-md border-gray-300 shadow-sm focus:border-violet-500 focus:ring-violet-500"></label>
            <label class="block text-sm font-semibold text-gray-700">来源和适用说明<textarea name="source_summary" rows="3" class="mt-2 block w-full rounded-md border-gray-300 shadow-sm focus:border-violet-500 focus:ring-violet-500">{{ old('source_summary', $specification?->source_summary) }}</textarea></label>
            <label class="block text-sm font-semibold text-gray-700">规则覆盖 JSON（仅填写需要覆盖的字段）<textarea name="rules_json" rows="14" class="mt-2 block w-full rounded-md border-gray-300 font-mono text-xs shadow-sm focus:border-violet-500 focus:ring-violet-500">{{ old('rules_json', $specification ? json_encode($specification->rules, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '{}') }}</textarea><span class="mt-2 block text-xs font-normal text-gray-500">可覆盖 title_max_chars、content_min_chars、content_max_chars、validation、image_rules、instructions 等字段。</span></label>
            <label class="block text-sm font-semibold text-gray-700">最近核验日期<input type="date" name="verified_at" value="{{ old('verified_at', $specification?->verified_at?->format('Y-m-d')) }}" class="mt-2 block w-full rounded-md border-gray-300 shadow-sm focus:border-violet-500 focus:ring-violet-500"></label>
            <div class="flex justify-end gap-3"><a href="{{ route('admin.content-platform-specifications.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700">取消</a><button class="rounded-md bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-700">保存平台规则</button></div>
        </form>
    </div>
@endsection
