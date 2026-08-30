<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateContentPlatformSpecificationRequest;
use App\Models\ContentPlatformSpecification;
use App\Models\ContentPlatformFeedback;
use App\Services\GeoFlow\ContentPlatformSpecificationResolver;
use App\Support\AdminWeb;
use App\Support\GeoFlow\ContentPlatformCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ContentPlatformSpecificationController extends Controller
{
    public function __construct(
        private readonly ContentPlatformCatalog $catalog,
        private readonly ContentPlatformSpecificationResolver $resolver,
    ) {}

    public function index(): View
    {
        $stored = ContentPlatformSpecification::query()->get()->keyBy('platform');
        $feedback = ContentPlatformFeedback::query()
            ->join('content_variants', 'content_variants.id', '=', 'content_platform_feedback.content_variant_id')
            ->selectRaw('content_variants.platform, content_platform_feedback.outcome, count(*) as total')
            ->groupBy('content_variants.platform', 'content_platform_feedback.outcome')
            ->get()
            ->groupBy('platform')
            ->map(fn ($rows) => $rows->pluck('total', 'outcome')->all());
        $items = collect($this->catalog->all())->map(function (array $rules, string $platform) use ($stored, $feedback): array {
            $specification = $stored->get($platform);
            $resolved = $this->resolver->resolve($platform);

            return [
                'platform' => $platform, 'specification' => $specification, 'resolved' => $resolved, 'defaults' => $rules,
                'feedback' => $feedback->get($platform, []),
            ];
        });

        return view('admin.content-platform-specifications.index', [
            'pageTitle' => '平台发布规则', 'activeMenu' => 'content_production',
            'adminSiteName' => AdminWeb::siteName(), 'items' => $items,
        ]);
    }

    public function edit(string $platform): View
    {
        abort_unless($this->catalog->get($platform) !== [], 404);
        $resolved = $this->resolver->resolve($platform);
        $specification = $resolved['specification'];
        $rules = $resolved['rules'];

        return view('admin.content-platform-specifications.edit', [
            'pageTitle' => '维护平台发布规则', 'activeMenu' => 'content_production', 'adminSiteName' => AdminWeb::siteName(),
            'platform' => $platform, 'specification' => $specification, 'rules' => $rules,
        ]);
    }

    public function update(UpdateContentPlatformSpecificationRequest $request, string $platform): RedirectResponse
    {
        abort_unless($this->catalog->get($platform) !== [], 404);
        $defaults = $this->catalog->get($platform);
        $data = $request->validated();
        $specification = ContentPlatformSpecification::query()->firstOrNew(['platform' => $platform]);
        $specification->fill([
            'label' => $data['label'], 'version' => $data['version'], 'status' => $data['status'],
            'source_url' => $data['source_url'] ?? null, 'source_summary' => $data['source_summary'] ?? null,
            'rules' => $data['rules'] ?? [], 'verified_at' => $data['verified_at'] ?? null, 'next_review_at' => $data['next_review_at'] ?? null,
        ]);
        if (! $specification->exists) {
            $specification->label = $data['label'] ?: (string) ($defaults['label'] ?? $platform);
        }
        $specification->save();

        return redirect()->route('admin.content-platform-specifications.index')->with('message', '平台规则已保存。后续生成会使用新版本；既有稿件继续使用原快照。');
    }
}
