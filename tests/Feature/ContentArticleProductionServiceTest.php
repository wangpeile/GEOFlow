<?php

namespace Tests\Feature;

use App\Contracts\GeoFlow\ContentSectionGenerator;
use App\Enums\ContentDirectionKind;
use App\Enums\ContentSectionStatus;
use App\Models\Admin;
use App\Models\Article;
use App\Models\ArticleVersion;
use App\Models\Author;
use App\Models\Category;
use App\Models\ContentDirectionVersion;
use App\Models\ContentProduction;
use App\Models\ContentSectionVersion;
use App\Models\Task;
use App\Services\GeoFlow\ArticleAssemblyService;
use App\Services\GeoFlow\ChineseContentGuard;
use App\Services\GeoFlow\SectionDraftingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class ContentArticleProductionServiceTest extends TestCase
{
    use RefreshDatabase;

    private FakeContentSectionGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->generator = new FakeContentSectionGenerator;
        $this->app->instance(ContentSectionGenerator::class, $this->generator);
    }

    public function test_section_failure_is_isolated_and_can_be_retried_without_losing_successes(): void
    {
        [$admin, $production, $nodes] = $this->context();
        $service = app(SectionDraftingService::class);
        $service->initialize($admin, $production);
        $this->generator->failKeys = [$nodes[1]['id']];

        foreach ($nodes as $node) {
            $service->generate($admin, $production, $node['id']);
        }

        $sections = $service->latestSections($production);
        $this->assertSame(2, $sections->where('status', ContentSectionStatus::Succeeded)->count());
        $this->assertSame(1, $sections->where('status', ContentSectionStatus::Failed)->count());
        $successfulContents = $sections
            ->where('status', ContentSectionStatus::Succeeded)
            ->pluck('content', 'section_key')
            ->all();

        $this->generator->failKeys = [];
        $retried = $service->generate($admin, $production, $nodes[1]['id'], true);

        $this->assertSame(ContentSectionStatus::Succeeded, $retried->status);
        $this->assertSame(2, $retried->version);
        foreach ($successfulContents as $sectionKey => $content) {
            $this->assertSame(
                $content,
                $service->latestSections($production)->firstWhere('section_key', $sectionKey)?->content,
            );
        }
    }

    public function test_manual_rewrite_creates_a_new_version_for_only_one_section(): void
    {
        [$admin, $production, $nodes] = $this->context();
        $service = app(SectionDraftingService::class);
        $service->initialize($admin, $production);
        foreach ($nodes as $node) {
            $service->saveManual($admin, $production, $node['id'], $this->sectionContent($node['heading']));
        }

        $before = $service->latestSections($production)->pluck('id', 'section_key');
        $updated = $service->saveManual(
            $admin,
            $production,
            $nodes[0]['id'],
            $this->sectionContent('更新后的章节'),
        );
        $after = $service->latestSections($production)->pluck('id', 'section_key');

        $this->assertSame(3, $updated->version);
        $this->assertNotSame($before[$nodes[0]['id']], $after[$nodes[0]['id']]);
        $this->assertSame($before[$nodes[1]['id']], $after[$nodes[1]['id']]);
        $this->assertSame($before[$nodes[2]['id']], $after[$nodes[2]['id']]);
    }

    public function test_assembly_is_ordered_idempotent_and_syncs_one_draft_article(): void
    {
        [$admin, $production, $nodes] = $this->context();
        $drafting = app(SectionDraftingService::class);
        $drafting->initialize($admin, $production);
        foreach ($nodes as $node) {
            $drafting->saveManual($admin, $production, $node['id'], $this->sectionContent($node['heading']));
        }

        $assembly = app(ArticleAssemblyService::class);
        $first = $assembly->assemble($admin, $production);
        $second = $assembly->assemble($admin, $production);

        $this->assertTrue($first->is($second));
        $this->assertSame(1, Article::query()->count());
        $this->assertSame(1, ArticleVersion::query()->count());
        $this->assertSame('draft', $first->article->status);
        $this->assertSame('pending', $first->article->review_status);
        $this->assertStringContainsString('## '.$nodes[0]['heading'], $first->body);
        $this->assertStringContainsString('## 常见问题', $first->body);
        $this->assertLessThan(
            mb_strpos($first->body, $nodes[1]['heading']),
            mb_strpos($first->body, $nodes[0]['heading']),
        );
        $this->assertNull($first->article->published_at);
    }

    public function test_revised_outline_reconciles_sections_and_preserves_only_unchanged_content(): void
    {
        [$admin, $production, $nodes] = $this->context();
        $drafting = app(SectionDraftingService::class);
        $drafting->initialize($admin, $production);
        foreach ($nodes as $node) {
            $drafting->saveManual($admin, $production, $node['id'], $this->sectionContent($node['heading']));
        }

        $originalSections = $drafting->latestSections($production)->keyBy('section_key');
        $currentOutline = ContentDirectionVersion::query()
            ->where('content_production_id', $production->id)
            ->where('kind', ContentDirectionKind::Outlines)
            ->latest('version')
            ->firstOrFail();
        $revisedNodes = $nodes;
        $revisedNodes[1]['heading'] = '如何建立可执行的选型标准';
        $revisedNodes[2]['position'] = 1;
        [$revisedNodes[0], $revisedNodes[2]] = [$revisedNodes[2], $revisedNodes[0]];
        $payload = $currentOutline->payload;
        $payload['candidates'][0]['nodes'] = $revisedNodes;
        $revisedOutline = ContentDirectionVersion::query()->create([
            'content_production_id' => $production->id,
            'kind' => ContentDirectionKind::Outlines,
            'version' => 2,
            'payload' => $payload,
            'input_hash' => hash('sha256', 'revised-outline'),
            'source_version_ids' => [$currentOutline->id],
            'created_by_admin_id' => $admin->id,
            'confirmed_by_admin_id' => $admin->id,
            'confirmed_at' => now(),
        ]);

        $reconciled = $drafting->initialize($admin, $production)->keyBy('section_key');
        $drafting->initialize($admin, $production);

        $this->assertSame($revisedOutline->id, $reconciled[$nodes[0]['id']]->outline_version_id);
        $this->assertSame(ContentSectionStatus::Succeeded, $reconciled[$nodes[0]['id']]->status);
        $this->assertSame($originalSections[$nodes[0]['id']]->content, $reconciled[$nodes[0]['id']]->content);
        $this->assertSame(ContentSectionStatus::Pending, $reconciled[$nodes[1]['id']]->status);
        $this->assertNull($reconciled[$nodes[1]['id']]->content);
        $this->assertSame(ContentSectionStatus::Succeeded, $reconciled[$nodes[2]['id']]->status);
        $this->assertSame(3, ContentSectionVersion::query()
            ->where('outline_version_id', $revisedOutline->id)
            ->count());

        $drafting->saveManual(
            $admin,
            $production,
            $nodes[1]['id'],
            $this->sectionContent($revisedNodes[1]['heading']),
        );
        $assembled = app(ArticleAssemblyService::class)->assemble($admin, $production);

        $this->assertStringContainsString('## '.$revisedNodes[1]['heading'], $assembled->body);
        $this->assertStringContainsString($originalSections[$nodes[0]['id']]->content, $assembled->body);
    }

    public function test_assembly_requires_explicit_article_ownership_and_guard_rejects_ai_instructions(): void
    {
        [$admin, $production, $nodes] = $this->context(false);
        $drafting = app(SectionDraftingService::class);
        $drafting->initialize($admin, $production);
        foreach ($nodes as $node) {
            $drafting->saveManual($admin, $production, $node['id'], $this->sectionContent($node['heading']));
        }

        $this->expectException(ValidationException::class);
        app(ArticleAssemblyService::class)->assemble($admin, $production);
    }

    public function test_chinese_guard_allows_technical_terms_but_rejects_model_meta_instructions(): void
    {
        $guard = app(ChineseContentGuard::class);
        $guard->validateField('content', '通过 OpenAI API 与 SEO 工具完成中文内容分析。');
        $this->assertTrue(true);

        $this->expectException(ValidationException::class);
        $guard->validateField('content', 'As an AI，我将输出一篇文章。');
    }

    public function test_section_generation_retries_once_after_invalid_english_reasoning(): void
    {
        [$admin, $production, $nodes] = $this->context();
        $service = app(SectionDraftingService::class);
        $service->initialize($admin, $production);
        $this->generator->queuedContents = [
            'The user wants me to write this section in Chinese. Let me analyze the evidence first.',
            $this->sectionContent($nodes[0]['heading']),
        ];

        $section = $service->generate($admin, $production, $nodes[0]['id']);

        $this->assertSame(ContentSectionStatus::Succeeded, $section->status);
        $this->assertSame(2, $this->generator->calls);
        $this->assertSame($this->sectionContent($nodes[0]['heading']), $section->content);
    }

    public function test_section_generation_stops_after_two_invalid_chinese_outputs(): void
    {
        [$admin, $production, $nodes] = $this->context();
        $service = app(SectionDraftingService::class);
        $service->initialize($admin, $production);
        $this->generator->queuedContents = [
            'The user wants me to write this section in Chinese.',
            'Let me analyze the evidence before writing the final answer.',
        ];

        $section = $service->generate($admin, $production, $nodes[0]['id']);

        $this->assertSame(ContentSectionStatus::Failed, $section->status);
        $this->assertSame(2, $this->generator->calls);
        $this->assertNull($section->content);
        $this->assertStringContainsString('英文模型说明', (string) $section->error_message);
    }

    public function test_section_generation_does_not_retry_provider_failures(): void
    {
        [$admin, $production, $nodes] = $this->context();
        $service = app(SectionDraftingService::class);
        $service->initialize($admin, $production);
        $this->generator->failKeys = [$nodes[0]['id']];

        $section = $service->generate($admin, $production, $nodes[0]['id']);

        $this->assertSame(ContentSectionStatus::Failed, $section->status);
        $this->assertSame(1, $this->generator->calls);
        $this->assertNull($section->content);
    }

    /**
     * @return array{Admin, ContentProduction, list<array{id:string, heading:string, level:string, evidence_ids:list<int>}>}
     */
    private function context(bool $withOwnership = true): array
    {
        $admin = Admin::query()->create([
            'username' => 'article-production-admin',
            'password' => 'secret',
            'role' => 'super_admin',
            'status' => 'active',
        ]);
        $category = Category::query()->create([
            'name' => '内容生产',
            'slug' => 'content-production',
        ]);
        $author = Author::query()->create([
            'name' => '内容团队',
        ]);
        $task = Task::query()->create([
            'name' => '分段写作任务',
            'fixed_category_id' => $withOwnership ? $category->id : null,
            'author_id' => $withOwnership ? $author->id : null,
        ]);
        $production = ContentProduction::query()->create([
            'uuid' => (string) Str::uuid(),
            'task_id' => $task->id,
            'created_by_admin_id' => $admin->id,
            'name' => '迭代四测试',
            'topic' => '企业视频会议系统',
            'mode' => 'guided',
            'status' => 'waiting_input',
            'current_stage' => 'section_writing',
            'language' => 'zh_CN',
            'context' => [
                'length_min' => 500,
                'length_max' => 5000,
            ],
        ]);
        $nodes = [
            ['id' => (string) Str::uuid(), 'heading' => '企业视频会议系统是什么', 'level' => 'h2', 'evidence_ids' => []],
            ['id' => (string) Str::uuid(), 'heading' => '如何建立选型标准', 'level' => 'h2', 'evidence_ids' => []],
            ['id' => (string) Str::uuid(), 'heading' => '实施过程中的常见问题', 'level' => 'h2', 'evidence_ids' => []],
        ];
        ContentDirectionVersion::query()->create([
            'content_production_id' => $production->id,
            'kind' => ContentDirectionKind::Titles,
            'version' => 1,
            'payload' => ['selected_title' => '企业视频会议系统选型与实施指南'],
            'input_hash' => hash('sha256', 'title'),
            'created_by_admin_id' => $admin->id,
            'confirmed_by_admin_id' => $admin->id,
            'confirmed_at' => now(),
        ]);
        $candidateId = (string) Str::uuid();
        ContentDirectionVersion::query()->create([
            'content_production_id' => $production->id,
            'kind' => ContentDirectionKind::Outlines,
            'version' => 1,
            'payload' => [
                'selected_id' => $candidateId,
                'candidates' => [
                    ['id' => $candidateId, 'nodes' => $nodes],
                ],
            ],
            'input_hash' => hash('sha256', 'outline'),
            'created_by_admin_id' => $admin->id,
            'confirmed_by_admin_id' => $admin->id,
            'confirmed_at' => now(),
        ]);

        return [$admin, $production, $nodes];
    }

    private function sectionContent(string $heading): string
    {
        return $heading.'围绕企业实际需求展开，说明目标、约束、实施步骤和验收方法。'
            .str_repeat('团队需要结合业务规模、网络条件、安全要求和维护能力形成清晰标准，并使用可核验的数据记录每一步决策。', 6);
    }
}

final class FakeContentSectionGenerator implements ContentSectionGenerator
{
    /** @var list<string> */
    public array $failKeys = [];

    /** @var list<string> */
    public array $queuedContents = [];

    public int $calls = 0;

    public function generate(ContentProduction $production, ContentSectionVersion $section): array
    {
        $this->calls++;

        if (in_array($section->section_key, $this->failKeys, true)) {
            throw new RuntimeException('模拟章节生成失败');
        }

        return [
            'content' => $this->queuedContents !== []
                ? array_shift($this->queuedContents)
                : $section->heading.'围绕企业实际需求展开。'
                    .str_repeat('团队应明确目标、核对依据、记录实施结果，并根据真实反馈持续调整方案。', 5),
            'model' => 'fake-section-model',
            'source' => 'test',
        ];
    }
}
