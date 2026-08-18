<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ExportContentVariantsRequest;
use App\Http\Requests\Admin\ReviewContentVariantRequest;
use App\Http\Requests\Admin\UpdateContentVariantRequest;
use App\Models\ContentGroup;
use App\Models\ContentVariant;
use App\Services\GeoFlow\ContentVariantExportService;
use App\Services\GeoFlow\ContentVariantWorkflowService;
use App\Support\AdminWeb;
use App\Support\GeoFlow\ContentPlatformCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ContentVariantController extends Controller
{
    public function __construct(
        private readonly ContentVariantWorkflowService $workflowService,
        private readonly ContentVariantExportService $exportService,
        private readonly ContentPlatformCatalog $platformCatalog,
    ) {}

    public function edit(ContentGroup $contentGroup, ContentVariant $contentVariant): View
    {
        $this->assertNested($contentGroup, $contentVariant);
        abort_if($contentVariant->platform === 'wordpress', 404);
        $contentVariant->load(['versions.creator:id,username', 'reviews.reviewer:id,username']);

        return view('admin.content-groups.variant-edit', [
            'pageTitle' => '编辑平台版本',
            'activeMenu' => 'content_production',
            'adminSiteName' => AdminWeb::siteName(),
            'contentGroup' => $contentGroup,
            'variant' => $contentVariant,
            'platform' => $this->platformCatalog->get($contentVariant->platform),
        ]);
    }

    public function update(UpdateContentVariantRequest $request, ContentGroup $contentGroup, ContentVariant $contentVariant): RedirectResponse
    {
        $this->assertNested($contentGroup, $contentVariant);
        try {
            $this->workflowService->update($contentVariant, $request->user('admin'), $request->validated());
        } catch (RuntimeException $exception) {
            return back()->withInput()->withErrors([$exception->getMessage()]);
        }

        return redirect()->route('admin.content-groups.show', $contentGroup)->with('message', '平台版本已保存为新版本，等待审核。');
    }

    public function review(ReviewContentVariantRequest $request, ContentGroup $contentGroup, ContentVariant $contentVariant): RedirectResponse
    {
        $this->assertNested($contentGroup, $contentVariant);
        try {
            $this->workflowService->review(
                $contentVariant,
                $request->user('admin'),
                (int) $request->validated('current_version'),
                $request->validated('decision'),
                $request->validated('note'),
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors([$exception->getMessage()]);
        }

        return back()->with('message', $request->validated('decision') === ContentVariant::REVIEW_APPROVED ? '平台版本已审核通过。' : '平台版本已退回修改。');
    }

    public function export(ExportContentVariantsRequest $request, ContentGroup $contentGroup): BinaryFileResponse|RedirectResponse
    {
        try {
            return $this->exportService->export($contentGroup, array_map('intval', $request->validated('variant_ids')));
        } catch (RuntimeException $exception) {
            return back()->withErrors([$exception->getMessage()]);
        }
    }

    private function assertNested(ContentGroup $contentGroup, ContentVariant $contentVariant): void
    {
        abort_unless($contentVariant->content_group_id === $contentGroup->id, 404);
    }
}
