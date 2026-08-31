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

        $message = match (true) {
            $report->status === 'fallback' => '未找到可用研究来源，已切换为知识库 / URL 证据模式。',
            $report->source_mode === 'ai_web_search' => '联网研究与内容缺口报告已生成。引用网页需先完成 URL 智能采集后才能作为文章证据。',
            default => '竞品研究与内容缺口报告已生成。',
        };

        return back()->with('message', $message);
    }
}
