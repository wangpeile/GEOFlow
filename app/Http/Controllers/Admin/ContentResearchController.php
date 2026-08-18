<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreContentResearchRequest;
use App\Models\ContentProduction;
use App\Services\GeoFlow\ContentResearchService;
use Illuminate\Http\RedirectResponse;

class ContentResearchController extends Controller
{
    public function store(
        StoreContentResearchRequest $request,
        ContentProduction $contentProduction,
        ContentResearchService $service,
    ): RedirectResponse {
        $report = $service->create($request->user('admin'), $contentProduction, $request->validated());

        return back()->with('message', $report->status === 'fallback'
            ? '未找到可用搜索来源，已切换为知识库 / URL 证据模式。'
            : '竞品研究与内容缺口报告已生成。');
    }
}
