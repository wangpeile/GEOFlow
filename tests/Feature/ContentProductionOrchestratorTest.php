<?php

namespace Tests\Feature;

use App\Enums\ContentProductionStatus;
use App\Enums\ContentStageStatus;
use App\Models\Admin;
use App\Models\ContentProduction;
use App\Models\ContentStageRun;
use App\Services\GeoFlow\ContentProductionOrchestrator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class ContentProductionOrchestratorTest extends TestCase
{
    private object $migration;

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
        Schema::create('tasks', function (Blueprint $table): void {
            $table->id();
        });
        Schema::create('articles', function (Blueprint $table): void {
            $table->id();
        });

        $this->migration = require database_path('migrations/2026_08_05_000000_create_content_production_tables.php');
        $this->migration->up();
    }

    protected function tearDown(): void
    {
        $this->migration->down();
        Schema::dropIfExists('articles');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('admins');

        parent::tearDown();
    }

    public function test_creation_is_idempotent_and_builds_the_ordered_stage_timeline(): void
    {
        $admin = $this->admin();
        $key = (string) Str::uuid();
        $attributes = [
            'idempotency_key' => $key,
            'name' => '视频会议系统选型',
            'topic' => '企业如何选择视频会议系统',
            'mode' => 'guided',
            'language' => 'zh_CN',
            'target_platforms' => ['wordpress', 'wechat'],
        ];

        $first = $this->orchestrator()->create($admin, $attributes);
        $second = $this->orchestrator()->create($admin, $attributes);

        $this->assertTrue($first->is($second));
        $this->assertSame(1, ContentProduction::query()->count());
        $this->assertSame(13, $first->stageRuns()->count());
        $this->assertSame(range(1, 13), $first->stageRuns()->pluck('sequence')->all());
        $this->assertSame('initialize', $first->stageRuns()->firstOrFail()->stage->value);
        $this->assertSame('platform_rewrite', $first->stageRuns()->reorder()->latest('sequence')->firstOrFail()->stage->value);
        $this->assertDatabaseHas('content_production_events', [
            'content_production_id' => $first->id,
            'event' => 'production_created',
            'admin_id' => $admin->id,
        ]);
    }

    public function test_failed_stage_retry_preserves_history_and_queues_one_new_attempt(): void
    {
        $admin = $this->admin();
        $production = $this->orchestrator()->create($admin, [
            'idempotency_key' => (string) Str::uuid(),
            'name' => '重试测试',
            'topic' => '重试测试主题',
            'mode' => 'guided',
            'language' => 'zh_CN',
        ]);
        $failedRun = $production->stageRuns()->firstOrFail();
        $failedRun->update([
            'status' => ContentStageStatus::Failed,
            'input_hash' => str_repeat('a', 64),
            'input_payload' => ['topic' => '重试测试主题'],
            'error_message' => '供应商暂时不可用',
        ]);
        $production->update([
            'status' => ContentProductionStatus::Failed,
            'last_error_message' => '供应商暂时不可用',
        ]);

        $retry = $this->orchestrator()->retry($admin, $production, $failedRun);
        $duplicateRetry = $this->orchestrator()->retry($admin, $production, $failedRun);

        $this->assertSame(2, $retry->attempt);
        $this->assertTrue($retry->is($duplicateRetry));
        $this->assertSame(ContentStageStatus::Pending, $retry->status);
        $this->assertSame($failedRun->stage, $retry->stage);
        $this->assertSame(['topic' => '重试测试主题'], $retry->input_payload);
        $this->assertSame(2, ContentStageRun::query()->where('stage', $failedRun->stage->value)->count());
        $this->assertSame(ContentStageStatus::Failed, $failedRun->fresh()->status);
        $this->assertSame(ContentProductionStatus::Queued, $production->fresh()->status);
        $this->assertDatabaseHas('content_production_events', [
            'content_production_id' => $production->id,
            'content_stage_run_id' => $retry->id,
            'event' => 'stage_retry_queued',
            'admin_id' => $admin->id,
        ]);
        $this->assertSame(1, $production->events()->where('event', 'stage_retry_queued')->count());
    }

    private function orchestrator(): ContentProductionOrchestrator
    {
        return app(ContentProductionOrchestrator::class);
    }

    private function admin(): Admin
    {
        return Admin::query()->create([
            'username' => 'content-production-admin',
            'password' => 'secret-123',
            'email' => 'content-production@example.com',
            'display_name' => 'Content Production Admin',
            'role' => 'super_admin',
            'status' => 'active',
        ]);
    }
}
