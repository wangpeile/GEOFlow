<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ContentSectionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RepairContentQualityRequest;
use App\Http\Requests\Admin\SaveContentSectionRequest;
use App\Models\ContentProduction;
use App\Models\QualityReport;
use App\Services\GeoFlow\ArticleAssemblyService;
use App\Services\GeoFlow\QualityGateService;
use App\Services\GeoFlow\SectionDraftingService;
use App\Services\GeoFlow\TargetedRepairService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class ContentArticleController extends Controller
{
    public function __construct(
        private readonly SectionDraftingService $sectionDraftingService,
        private readonly ArticleAssemblyService $articleAssemblyService,
        private readonly QualityGateService $qualityGateService,
        private readonly TargetedRepairService $targetedRepairService,
    ) {}

    public function initialize(ContentProduction $contentProduction): RedirectResponse
    {
        $this->ensureEnabled();
        $this->sectionDraftingService->initialize(Auth::guard('admin')->user(), $contentProduction);

        return back()->with('message', '已根据确认大纲建立章节草稿。');
    }

    public function generateAll(ContentProduction $contentProduction): RedirectResponse
    {
        $this->ensureEnabled();
        $admin = Auth::guard('admin')->user();
        $sections = $this->sectionDraftingService->initialize($admin, $contentProduction);
        foreach ($sections as $section) {
            $this->sectionDraftingService->generate(
                $admin,
                $contentProduction,
                $section->section_key,
            );
        }

        $latest = $this->sectionDraftingService->latestSections($contentProduction);
        $failed = $latest->where('status', ContentSectionStatus::Failed)->count();

        return back()->with(
            $failed > 0 ? 'error' : 'message',
            $failed > 0
                ? "章节生成完成，其中 {$failed} 个章节失败，可单独重试。"
                : '全部章节已生成。',
        );
    }

    public function generate(ContentProduction $contentProduction, string $sectionKey): RedirectResponse
    {
        $this->ensureEnabled();
        $section = $this->sectionDraftingService->generate(
            Auth::guard('admin')->user(),
            $contentProduction,
            $sectionKey,
            true,
        );

        return back()->with(
            $section->status === ContentSectionStatus::Succeeded ? 'message' : 'error',
            $section->status === ContentSectionStatus::Succeeded
                ? '该章节已生成新版本。'
                : '该章节生成失败，其他章节内容未受影响。',
        );
    }

    public function save(
        SaveContentSectionRequest $request,
        ContentProduction $contentProduction,
        string $sectionKey,
    ): RedirectResponse {
        $this->ensureEnabled();
        $this->sectionDraftingService->saveManual(
            Auth::guard('admin')->user(),
            $contentProduction,
            $sectionKey,
            $request->validated('content'),
        );

        return back()->with('message', '章节手工版本已保存。');
    }

    public function assemble(ContentProduction $contentProduction): RedirectResponse
    {
        $this->ensureEnabled();
        $version = $this->articleAssemblyService->assemble(
            Auth::guard('admin')->user(),
            $contentProduction,
        );

        return back()->with('message', "文章草稿已组装并同步（版本 {$version->version}）。");
    }

    public function inspectQuality(ContentProduction $contentProduction): RedirectResponse
    {
        $this->ensureEnabled();
        $report = $this->qualityGateService->inspect(
            Auth::guard('admin')->user(),
            $contentProduction,
        );

        return back()->with(
            $report->status->value === 'blocked' ? 'error' : 'message',
            "质量检查完成：{$report->summary['blockers']} 个阻断项，{$report->summary['warnings']} 个警告项。",
        );
    }

    public function repairQuality(
        RepairContentQualityRequest $request,
        ContentProduction $contentProduction,
        QualityReport $qualityReport,
    ): RedirectResponse {
        $this->ensureEnabled();
        abort_unless($qualityReport->content_production_id === $contentProduction->id, 404);
        $result = $this->targetedRepairService->repair(
            Auth::guard('admin')->user(),
            $contentProduction,
            $qualityReport,
            $request->validated('issue_ids'),
        );

        return back()->with(
            $result['quality_report']->status->value === 'blocked' ? 'error' : 'message',
            "定向修复完成并已重新检查（文章版本 {$result['article_version']->version}）。",
        );
    }

    private function ensureEnabled(): void
    {
        abort_unless((bool) config('geoflow.content_production_pipeline_enabled', false), 404);
    }
}
