<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreWritingRuleRequest;
use App\Models\ArticleType;
use App\Models\KnowledgeBase;
use App\Models\SensitiveWord;
use App\Models\WritingRule;
use App\Services\GeoFlow\WritingRulePresetService;
use App\Services\GeoFlow\WritingRuleVersionService;
use App\Support\AdminWeb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class WritingRuleController extends Controller
{
    public function __construct(
        private readonly WritingRuleVersionService $versions,
        private readonly WritingRulePresetService $presets,
    ) {}

    public function index(): View
    {
        $rules = WritingRule::query()
            ->with(['articleType:id,name', 'createdBy:id,username,display_name'])
            ->latest('updated_at')
            ->paginate((int) config('geoflow.admin_items_per_page', 20));

        return view('admin.writing-rules.index', $this->viewData(['rules' => $rules]));
    }

    public function create(): View
    {
        return view('admin.writing-rules.form', $this->formData());
    }

    public function store(StoreWritingRuleRequest $request): RedirectResponse
    {
        $rule = $this->versions->create(Auth::guard('admin')->user(), $request->validated());

        return redirect()->route('admin.writing-rules.show', $rule)->with('message', '写作规则已创建。');
    }

    public function show(WritingRule $writingRule): View
    {
        $writingRule->load(['articleType', 'createdBy:id,username,display_name', 'versions.createdBy:id,username,display_name']);

        return view('admin.writing-rules.show', $this->viewData(['rule' => $writingRule]));
    }

    public function edit(WritingRule $writingRule): View
    {
        $writingRule->load(['articleType', 'versions']);

        return view('admin.writing-rules.form', $this->formData($writingRule));
    }

    public function update(StoreWritingRuleRequest $request, WritingRule $writingRule): RedirectResponse
    {
        $this->versions->update(Auth::guard('admin')->user(), $writingRule, $request->validated());

        return redirect()->route('admin.writing-rules.show', $writingRule)->with('message', '写作规则已保存为新版本。');
    }

    public function installPresets(): RedirectResponse
    {
        $count = $this->presets->install(Auth::guard('admin')->user());

        return back()->with('message', $count > 0 ? "已安装 {$count} 套预设规则。" : '系统预设已安装，无需重复操作。');
    }

    /** @return array<string, mixed> */
    private function formData(?WritingRule $rule = null): array
    {
        return $this->viewData([
            'rule' => $rule,
            'settings' => $rule?->currentVersionRecord()?->settings ?? [],
            'articleTypes' => ArticleType::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'knowledgeBases' => KnowledgeBase::query()->orderBy('name')->get(['id', 'name']),
            'sensitiveWords' => SensitiveWord::query()->where('is_enabled', true)->orderBy('word')->limit(100)->get(['id', 'word', 'severity']),
        ]);
    }

    /** @param array<string, mixed> $data */
    private function viewData(array $data): array
    {
        return array_merge(['pageTitle' => '写作规则', 'activeMenu' => 'articles', 'adminSiteName' => AdminWeb::siteName()], $data);
    }
}
