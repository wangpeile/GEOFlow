<?php

namespace Tests\Feature;

use App\Enums\ContentDirectionKind;
use App\Enums\ContentEvidenceSourceType;
use App\Enums\ContentEvidenceUsage;
use App\Enums\ContentProductionStage;
use App\Enums\ContentStageStatus;
use App\Ai\Agents\MarkdownContentWriterAgent;
use App\Models\Admin;
use App\Models\AiModel;
use App\Models\ContentDirectionVersion;
use App\Models\ContentEvidence;
use App\Models\ContentProduction;
use App\Services\GeoFlow\ContentDirectionService;
use App\Services\GeoFlow\ContentProductionOrchestrator;
use App\Support\GeoFlow\ApiKeyCrypto;
use Illuminate\Database\Schema\Blueprint;
use Laravel\Ai\Ai;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ContentDirectionServiceTest extends TestCase
{
    private bool $createdAiModelsTable = false;

    private object $productionMigration;

    private object $directionMigration;

    private object $stageInvalidationMigration;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('admins', function (Blueprint $table): void {
            $table->id();
            $table->string('username')->unique();
            $table->string('password');
            $table->string('email')->nullable();
            $table->string('display_name')->nullable();
            $table->string('role')->default('admin');
            $table->string('status')->default('active');
            $table->timestamps();
        });
        if (! Schema::hasTable('ai_models')) {
            Schema::create('ai_models', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('version')->default('');
                $table->string('api_key', 500)->default('');
                $table->string('model_id');
                $table->string('model_type')->nullable();
                $table->string('api_url', 500)->default('');
                $table->integer('failover_priority')->default(100);
                $table->integer('daily_limit')->default(0);
                $table->integer('used_today')->default(0);
                $table->integer('total_used')->default(0);
                $table->string('status')->default('active');
                $table->timestamps();
            });
            $this->createdAiModelsTable = true;
        }
        Schema::create('tasks', fn (Blueprint $table) => $table->id());
        Schema::create('articles', fn (Blueprint $table) => $table->id());
        $this->productionMigration = require database_path('migrations/2026_08_05_000000_create_content_production_tables.php');
        $this->productionMigration->up();

        Schema::create('content_evidences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('content_production_id');
            $table->unsignedBigInteger('knowledge_base_id')->nullable();
            $table->unsignedBigInteger('knowledge_chunk_id')->nullable();
            $table->unsignedBigInteger('url_import_job_id')->nullable();
            $table->foreignId('created_by_admin_id')->nullable();
            $table->string('source_type', 30);
            $table->string('usage', 30);
            $table->string('source_key');
            $table->text('source_url')->nullable();
            $table->string('source_title')->nullable();
            $table->longText('content_snapshot');
            $table->text('excerpt')->nullable();
            $table->decimal('confidence', 6, 5)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('collected_at')->nullable();
            $table->timestamps();
        });

        $this->directionMigration = require database_path('migrations/2026_08_05_020000_create_content_direction_versions_table.php');
        $this->directionMigration->up();
        $this->stageInvalidationMigration = require database_path('migrations/2026_08_05_020100_add_invalidation_to_content_stage_runs.php');
        $this->stageInvalidationMigration->up();
    }

    protected function tearDown(): void
    {
        $this->stageInvalidationMigration->down();
        $this->directionMigration->down();
        Schema::dropIfExists('content_evidences');
        $this->productionMigration->down();
        Schema::dropIfExists('articles');
        Schema::dropIfExists('tasks');
        if ($this->createdAiModelsTable) {
            Schema::dropIfExists('ai_models');
        }
        Schema::dropIfExists('admins');

        parent::tearDown();
    }

    public function test_guided_flow_requires_confirmation_and_keeps_all_versions(): void
    {
        [$admin, $production] = $this->context();
        $this->evidence($admin, $production, '产品白皮书');
        $service = app(ContentDirectionService::class);

        $brief = $service->generateBrief($admin, $production);
        $this->assertContains('产品白皮书', $brief->payload['must_cover']);
        $this->assertSame(ContentStageStatus::WaitingInput, $this->stage($production, ContentProductionStage::Brief)->status);

        try {
            $service->generateTitles($admin, $production);
            $this->fail('向导模式未确认简报时不应继续生成标题。');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $service->confirm($admin, $production, ContentDirectionKind::Brief);
        $titles = $service->generateTitles($admin, $production);
        $this->assertCount(5, $titles->payload['candidates']);
        $selected = $service->selectTitle(
            $admin,
            $production,
            $titles->payload['candidates'][0]['id'],
            null,
        );
        $service->confirm($admin, $production, ContentDirectionKind::Titles);
        $outlines = $service->generateOutlines($admin, $production);

        $this->assertCount(2, $outlines->payload['candidates']);
        $this->assertSame(2, ContentDirectionVersion::query()->where('kind', 'titles')->count());
        $this->assertNotNull($selected->input_hash);
        $this->assertSame(ContentStageStatus::WaitingInput, $this->stage($production, ContentProductionStage::Outline)->status);
    }

    public function test_titles_and_outlines_prefer_the_active_ai_model_and_record_the_source(): void
    {
        [$admin, $production] = $this->context();
        $this->evidence($admin, $production, '视频会议采购指南');
        $this->activeWritingModel();
        Ai::fakeAgent(MarkdownContentWriterAgent::class, [
            json_encode([
                'candidates' => [
                    ['title' => '企业视频会议系统怎么选：采购前的五个关键判断', 'rationale' => '匹配方案评估意图'],
                    ['title' => '企业视频会议系统选型指南：功能、部署与成本', 'rationale' => '覆盖采购决策维度'],
                    ['title' => '视频会议系统选购避坑：从需求到上线的实用方法', 'rationale' => '适合问题解决型读者'],
                    ['title' => '企业视频会议平台对比：建立可执行的选择标准', 'rationale' => '突出对比与判断标准'],
                    ['title' => '采购企业视频会议系统前，先完成这份检查清单', 'rationale' => '强调可执行行动'],
                ],
            ], JSON_UNESCAPED_UNICODE),
            json_encode([
                'candidates' => [
                    [
                        'name' => '采购决策路径',
                        'nodes' => [
                            ['level' => 'h2', 'heading' => '明确企业视频会议系统的采购目标'],
                            ['level' => 'h2', 'heading' => '梳理业务与技术需求'],
                            ['level' => 'h3', 'heading' => '识别关键使用场景'],
                            ['level' => 'h2', 'heading' => '比较部署、安全与成本'],
                            ['level' => 'h2', 'heading' => '制定上线与验收计划'],
                        ],
                    ],
                    [
                        'name' => '风险控制路径',
                        'nodes' => [
                            ['level' => 'h2', 'heading' => '采购前最容易忽略的风险'],
                            ['level' => 'h2', 'heading' => '建立产品评估标准'],
                            ['level' => 'h3', 'heading' => '验证服务与支持能力'],
                            ['level' => 'h2', 'heading' => '设计试点与迁移安排'],
                            ['level' => 'h2', 'heading' => '形成最终决策清单'],
                        ],
                    ],
                ],
            ], JSON_UNESCAPED_UNICODE),
        ]);

        $service = app(ContentDirectionService::class);
        $service->generateBrief($admin, $production);
        $service->confirm($admin, $production, ContentDirectionKind::Brief);
        $titles = $service->generateTitles($admin, $production);

        $this->assertSame('laravel_ai_sdk', $titles->payload['generation_source']);
        $this->assertCount(5, $titles->payload['candidates']);
        $service->selectTitle($admin, $production, $titles->payload['candidates'][0]['id'], null);
        $service->confirm($admin, $production, ContentDirectionKind::Titles);
        $outlines = $service->generateOutlines($admin, $production);

        $this->assertSame('laravel_ai_sdk', $outlines->payload['generation_source']);
        $this->assertCount(2, $outlines->payload['candidates']);
        Ai::assertAgentWasPrompted(MarkdownContentWriterAgent::class, fn ($prompt): bool => str_contains($prompt->prompt, '主题：企业视频会议系统'));
    }

    public function test_upstream_edit_invalidates_downstream_versions_and_stage_runs_without_overwriting_history(): void
    {
        [$admin, $production] = $this->context();
        $evidence = $this->evidence($admin, $production, '可信资料');
        $service = app(ContentDirectionService::class);

        $brief = $service->generateBrief($admin, $production);
        $service->confirm($admin, $production, ContentDirectionKind::Brief);
        $titles = $service->generateTitles($admin, $production);
        $service->selectTitle($admin, $production, $titles->payload['candidates'][0]['id'], null);
        $service->confirm($admin, $production, ContentDirectionKind::Titles);
        $outline = $service->generateOutlines($admin, $production);

        $service->saveBrief($admin, $production, [
            'article_type' => '操作指南',
            'target_audience' => '企业采购负责人',
            'search_intent' => '选择方案',
            'content_angle' => '以实施风险为主线',
            'must_cover' => '部署步骤',
            'avoid_topics' => '无依据承诺',
            'evidence_ids' => [$evidence->id],
        ]);

        $this->assertNotNull($outline->fresh()->invalidated_at);
        $this->assertNotNull($this->stage($production, ContentProductionStage::Outline)->invalidated_at);
        $this->assertSame(2, ContentDirectionVersion::query()->where('kind', 'brief')->count());
        $this->assertNotNull($brief->fresh()->confirmed_at);
    }

    public function test_outline_nodes_can_be_selected_edited_reordered_and_regenerated_as_new_versions(): void
    {
        [$admin, $production] = $this->context();
        $this->evidence($admin, $production, '实施手册');
        $service = app(ContentDirectionService::class);
        $brief = $service->generateBrief($admin, $production);
        $service->confirm($admin, $production, ContentDirectionKind::Brief);
        $titles = $service->generateTitles($admin, $production);
        $service->selectTitle($admin, $production, null, '企业视频会议系统选型指南');
        $service->confirm($admin, $production, ContentDirectionKind::Titles);
        $outlines = $service->generateOutlines($admin, $production);
        $candidate = $outlines->payload['candidates'][0];
        $node = $candidate['nodes'][1];

        $selected = $service->updateOutline($admin, $production, [
            'candidate_id' => $candidate['id'],
            'action' => 'select',
        ]);
        $edited = $service->updateOutline($admin, $production, [
            'candidate_id' => $candidate['id'],
            'node_id' => $node['id'],
            'action' => 'edit',
            'level' => 'h2',
            'heading' => '如何建立可执行的选型标准',
        ]);
        $regenerated = $service->updateOutline($admin, $production, [
            'candidate_id' => $candidate['id'],
            'node_id' => $node['id'],
            'action' => 'regenerate',
        ]);

        $this->assertSame($candidate['id'], $selected->payload['selected_id']);
        $this->assertStringContainsString(
            '如何建立可执行的选型标准',
            json_encode($edited->payload, JSON_UNESCAPED_UNICODE)
        );
        $this->assertStringContainsString(
            '实践建议',
            json_encode($regenerated->payload, JSON_UNESCAPED_UNICODE)
        );
        $this->assertSame(4, ContentDirectionVersion::query()->where('kind', 'outlines')->count());
    }

    /**
     * @return array{Admin, ContentProduction}
     */
    private function context(): array
    {
        $admin = Admin::query()->create([
            'username' => 'direction-admin',
            'password' => 'secret',
            'role' => 'super_admin',
            'status' => 'active',
        ]);
        $production = app(ContentProductionOrchestrator::class)->create($admin, [
            'idempotency_key' => (string) Str::uuid(),
            'name' => '内容方向测试',
            'topic' => '企业视频会议系统',
            'mode' => 'guided',
            'language' => 'zh_CN',
        ]);

        return [$admin, $production];
    }

    private function evidence(Admin $admin, ContentProduction $production, string $title): ContentEvidence
    {
        return ContentEvidence::query()->create([
            'content_production_id' => $production->id,
            'created_by_admin_id' => $admin->id,
            'source_type' => ContentEvidenceSourceType::Manual,
            'usage' => ContentEvidenceUsage::MustCite,
            'source_key' => (string) Str::uuid(),
            'source_title' => $title,
            'content_snapshot' => $title.'正文快照',
            'collected_at' => now(),
        ]);
    }

    private function activeWritingModel(): AiModel
    {
        return AiModel::query()->create([
            'name' => '内容策划测试模型',
            'version' => 'test',
            'api_key' => app(ApiKeyCrypto::class)->encrypt('test-key'),
            'model_id' => 'gpt-test',
            'model_type' => 'chat',
            'api_url' => 'https://api.openai.com/v1',
            'failover_priority' => 1,
            'status' => 'active',
        ]);
    }

    private function stage(ContentProduction $production, ContentProductionStage $stage)
    {
        return $production->stageRuns()->where('stage', $stage->value)->firstOrFail()->fresh();
    }
}
