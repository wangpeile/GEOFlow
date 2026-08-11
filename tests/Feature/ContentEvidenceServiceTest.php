<?php

namespace Tests\Feature;

use App\Enums\ContentEvidenceUsage;
use App\Models\Admin;
use App\Models\ContentProduction;
use App\Models\KnowledgeBase;
use App\Models\KnowledgeChunk;
use App\Models\UrlImportJob;
use App\Services\GeoFlow\ContentEvidenceService;
use App\Services\GeoFlow\ContentProductionOrchestrator;
use App\Services\GeoFlow\KnowledgeRetrievalService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class ContentEvidenceServiceTest extends TestCase
{
    private object $productionMigration;

    private object $evidenceMigration;

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
        Schema::create('tasks', fn (Blueprint $table) => $table->id());
        Schema::create('articles', fn (Blueprint $table) => $table->id());
        Schema::create('knowledge_bases', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('content')->default('');
            $table->string('source_url', 500)->nullable();
            $table->timestamps();
        });
        Schema::create('knowledge_chunks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('knowledge_base_id')->constrained()->cascadeOnDelete();
            $table->integer('chunk_index');
            $table->text('content');
            $table->string('content_hash', 64)->default('');
            $table->string('chunk_title')->default('');
            $table->string('section_path')->default('');
            $table->text('metadata_json')->nullable();
            $table->integer('token_count')->default(0);
            $table->text('embedding_json')->nullable();
            $table->integer('embedding_model_id')->nullable();
            $table->integer('embedding_dimensions')->default(0);
            $table->timestamps();
        });
        Schema::create('url_import_jobs', function (Blueprint $table): void {
            $table->id();
            $table->text('url');
            $table->text('normalized_url');
            $table->string('source_domain')->default('');
            $table->string('page_title')->default('');
            $table->string('status')->default('queued');
            $table->text('result_json')->default('');
            $table->string('created_by')->default('');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        $this->productionMigration = require database_path('migrations/2026_08_05_000000_create_content_production_tables.php');
        $this->productionMigration->up();
        $this->evidenceMigration = require database_path('migrations/2026_08_05_010000_create_content_evidences_table.php');
        $this->evidenceMigration->up();
    }

    protected function tearDown(): void
    {
        $this->evidenceMigration->down();
        $this->productionMigration->down();
        Schema::dropIfExists('url_import_jobs');
        Schema::dropIfExists('knowledge_chunks');
        Schema::dropIfExists('knowledge_bases');
        Schema::dropIfExists('articles');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('admins');

        parent::tearDown();
    }

    public function test_knowledge_retrieval_saves_a_traceable_content_snapshot_idempotently(): void
    {
        $admin = $this->admin();
        $production = $this->production($admin);
        $knowledgeBase = KnowledgeBase::query()->create([
            'name' => '产品知识库',
            'content' => '完整资料',
            'source_url' => 'https://example.com/manual',
        ]);
        $chunk = KnowledgeChunk::query()->create([
            'knowledge_base_id' => $knowledgeBase->id,
            'chunk_index' => 1,
            'content' => '这是一条可离线检索并保存的产品参数证据。',
            'chunk_title' => '产品参数',
        ]);
        $retrieval = Mockery::mock(KnowledgeRetrievalService::class);
        $retrieval->shouldReceive('retrieveEvidence')->twice()->andReturn([[
            'knowledge_chunk_id' => $chunk->id,
            'chunk_index' => 1,
            'content' => $chunk->content,
            'chunk_title' => $chunk->chunk_title,
            'section_path' => '',
            'metadata' => ['source_url' => 'https://example.com/manual'],
            'score' => 0.91,
            'vector_score' => 0.9,
            'keyword_score' => 0.8,
            'title_score' => 1.0,
        ]]);
        $service = new ContentEvidenceService($retrieval);

        $first = $service->retrieveKnowledge($admin, $production, $knowledgeBase, '产品参数')->firstOrFail();
        $service->updateUsage($admin, $production, $first, ContentEvidenceUsage::MustCite);
        $service->retrieveKnowledge($admin, $production, $knowledgeBase, '产品参数');

        $this->assertSame(1, $production->evidences()->count());
        $evidence = $production->evidences()->firstOrFail();
        $this->assertSame($chunk->content, $evidence->content_snapshot);
        $this->assertSame($chunk->id, $evidence->knowledge_chunk_id);
        $this->assertSame('https://example.com/manual', $evidence->source_url);
        $this->assertEqualsWithDelta(0.91, $evidence->confidence, 0.00001);
        $this->assertSame(ContentEvidenceUsage::MustCite, $evidence->usage);
    }

    public function test_completed_url_import_and_manual_evidence_share_the_same_evidence_list(): void
    {
        $admin = $this->admin();
        $production = $this->production($admin);
        $job = UrlImportJob::query()->create([
            'url' => 'https://example.com/article',
            'normalized_url' => 'https://example.com/article',
            'source_domain' => 'example.com',
            'page_title' => '采集文章',
            'status' => 'completed',
            'result_json' => json_encode([
                'source' => ['fetched_at' => now()->toIso8601String(), 'status' => 200],
                'page' => ['title' => '采集文章', 'summary' => '摘要', 'text' => 'URL 页面正文快照。'],
            ], JSON_UNESCAPED_UNICODE),
            'created_by' => $admin->username,
            'finished_at' => now(),
        ]);
        $service = new ContentEvidenceService(Mockery::mock(KnowledgeRetrievalService::class));

        $service->attachUrlImport($admin, $production, $job);
        $manual = $service->addManual($admin, $production, [
            'source_title' => '访谈记录',
            'source_url' => null,
            'content_snapshot' => '人工补充的事实。',
            'usage' => 'must_cite',
        ]);
        $service->updateUsage($admin, $production, $manual, ContentEvidenceUsage::Disabled);

        $this->assertSame(2, $production->evidences()->count());
        $this->assertSame('URL 页面正文快照。', $production->evidences()->where('url_import_job_id', $job->id)->firstOrFail()->content_snapshot);
        $this->assertSame(ContentEvidenceUsage::Disabled, $manual->fresh()->usage);
        $this->assertSame(1, $production->events()->where('event', 'url_evidence_attached')->count());
        $this->assertSame(1, $production->events()->where('event', 'manual_evidence_added')->count());

        $service->delete($admin, $production, $manual->fresh());
        $this->assertSame(1, $production->events()->where('event', 'evidence_deleted')->count());
        $this->assertSame(1, $production->evidences()->count());
    }

    public function test_empty_retrieval_stops_with_an_explicit_warning(): void
    {
        $admin = $this->admin();
        $production = $this->production($admin);
        $knowledgeBase = KnowledgeBase::query()->create(['name' => '空知识库', 'content' => '']);
        $retrieval = Mockery::mock(KnowledgeRetrievalService::class);
        $retrieval->shouldReceive('retrieveEvidence')->once()->andReturn([]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('未检索到可用证据');

        (new ContentEvidenceService($retrieval))->retrieveKnowledge($admin, $production, $knowledgeBase, '不存在的资料');
    }

    private function admin(): Admin
    {
        return Admin::query()->create([
            'username' => 'evidence-admin',
            'password' => 'secret-123',
            'role' => 'super_admin',
            'status' => 'active',
        ]);
    }

    private function production(Admin $admin): ContentProduction
    {
        return app(ContentProductionOrchestrator::class)->create($admin, [
            'idempotency_key' => (string) Str::uuid(),
            'name' => '证据中心测试',
            'topic' => '本地证据检索',
            'mode' => 'guided',
            'language' => 'zh_CN',
        ]);
    }
}
