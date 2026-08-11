<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ContentEvidenceUsage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AttachUrlContentEvidenceRequest;
use App\Http\Requests\Admin\RetrieveContentEvidenceRequest;
use App\Http\Requests\Admin\StoreContentEvidenceRequest;
use App\Http\Requests\Admin\UpdateContentEvidenceRequest;
use App\Models\ContentEvidence;
use App\Models\ContentProduction;
use App\Models\KnowledgeBase;
use App\Models\UrlImportJob;
use App\Services\GeoFlow\ContentEvidenceService;
use Illuminate\Http\RedirectResponse;

class ContentEvidenceController extends Controller
{
    public function __construct(private readonly ContentEvidenceService $evidenceService) {}

    public function retrieve(RetrieveContentEvidenceRequest $request, ContentProduction $contentProduction): RedirectResponse
    {
        $validated = $request->validated();
        $knowledgeBase = KnowledgeBase::query()->findOrFail($validated['knowledge_base_id']);
        $count = $this->evidenceService
            ->retrieveKnowledge(
                $request->user('admin'),
                $contentProduction,
                $knowledgeBase,
                $validated['query'],
                (int) ($validated['limit'] ?? 5),
            )
            ->count();

        return back()->with('message', "已保存 {$count} 条知识库证据。");
    }

    public function attachUrl(AttachUrlContentEvidenceRequest $request, ContentProduction $contentProduction): RedirectResponse
    {
        $job = UrlImportJob::query()->findOrFail($request->validated('url_import_job_id'));
        $this->evidenceService->attachUrlImport($request->user('admin'), $contentProduction, $job);

        return back()->with('message', 'URL 采集资料已加入证据中心。');
    }

    public function store(StoreContentEvidenceRequest $request, ContentProduction $contentProduction): RedirectResponse
    {
        $this->evidenceService->addManual($request->user('admin'), $contentProduction, $request->validated());

        return back()->with('message', '手工证据已添加。');
    }

    public function update(
        UpdateContentEvidenceRequest $request,
        ContentProduction $contentProduction,
        ContentEvidence $contentEvidence,
    ): RedirectResponse {
        $this->ensureBelongsToProduction($contentProduction, $contentEvidence);
        $this->evidenceService->updateUsage(
            $request->user('admin'),
            $contentProduction,
            $contentEvidence,
            ContentEvidenceUsage::from($request->validated('usage')),
        );

        return back()->with('message', '证据用途已更新。');
    }

    public function destroy(ContentProduction $contentProduction, ContentEvidence $contentEvidence): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->canManageProtectedWorkflows(), 403);
        abort_unless((bool) config('geoflow.content_production_pipeline_enabled', false), 404);
        $this->ensureBelongsToProduction($contentProduction, $contentEvidence);
        $this->evidenceService->delete(auth('admin')->user(), $contentProduction, $contentEvidence);

        return back()->with('message', '证据已删除。');
    }

    private function ensureBelongsToProduction(ContentProduction $production, ContentEvidence $evidence): void
    {
        abort_unless($evidence->content_production_id === $production->getKey(), 404);
    }
}
