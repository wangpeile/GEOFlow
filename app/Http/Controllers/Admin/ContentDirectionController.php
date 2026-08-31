<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ContentDirectionKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveContentBriefRequest;
use App\Http\Requests\Admin\SelectContentTitleRequest;
use App\Http\Requests\Admin\UpdateContentOutlineRequest;
use App\Models\ContentProduction;
use App\Services\GeoFlow\ContentDirectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class ContentDirectionController extends Controller
{
    public function __construct(private readonly ContentDirectionService $service) {}

    public function generateBrief(ContentProduction $contentProduction): RedirectResponse
    {
        $this->ensureEnabled();
        $this->service->generateBrief(Auth::guard('admin')->user(), $contentProduction);

        return back()->with('message', '已根据当前证据生成内容简报草稿。');
    }

    public function saveBrief(
        SaveContentBriefRequest $request,
        ContentProduction $contentProduction,
    ): RedirectResponse {
        $this->ensureEnabled();
        $this->service->saveBrief(Auth::guard('admin')->user(), $contentProduction, $request->validated());

        return back()->with('message', '内容简报已保存为新版本。');
    }

    public function generateTitles(ContentProduction $contentProduction): RedirectResponse
    {
        $this->ensureEnabled();
        $this->service->generateTitles(Auth::guard('admin')->user(), $contentProduction);

        return back()->with('message', '已生成 5 个简体中文标题候选；模型不可用时会明确标记为规则生成。');
    }

    public function selectTitle(
        SelectContentTitleRequest $request,
        ContentProduction $contentProduction,
    ): RedirectResponse {
        $this->ensureEnabled();
        $this->service->selectTitle(
            Auth::guard('admin')->user(),
            $contentProduction,
            $request->validated('candidate_id'),
            $request->validated('custom_title'),
        );

        return back()->with('message', '标题已保存为新版本。');
    }

    public function generateOutlines(ContentProduction $contentProduction): RedirectResponse
    {
        $this->ensureEnabled();
        $this->service->generateOutlines(Auth::guard('admin')->user(), $contentProduction);

        return back()->with('message', '已生成两个可比较的大纲候选；模型不可用时会明确标记为规则生成。');
    }

    public function updateOutline(
        UpdateContentOutlineRequest $request,
        ContentProduction $contentProduction,
    ): RedirectResponse {
        $this->ensureEnabled();
        $this->service->updateOutline(Auth::guard('admin')->user(), $contentProduction, $request->validated());

        return back()->with('message', '大纲调整已保存为新版本。');
    }

    public function confirm(
        ContentProduction $contentProduction,
        ContentDirectionKind $kind,
    ): RedirectResponse {
        $this->ensureEnabled();
        $this->service->confirm(Auth::guard('admin')->user(), $contentProduction, $kind);

        return back()->with('message', '当前版本已确认，后台任务不会静默覆盖。');
    }

    private function ensureEnabled(): void
    {
        abort_unless((bool) config('geoflow.content_production_pipeline_enabled', false), 404);
    }
}
