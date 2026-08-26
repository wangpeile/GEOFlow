<?php

namespace Tests\Feature;

use App\Enums\TaskPipelineMode;
use App\Enums\TaskScheduleStatus;
use App\Jobs\ProcessStandardContentProductionJob;
use App\Models\Admin;
use App\Models\ContentAutomationRun;
use App\Models\ContentTopic;
use App\Models\ContentTopicIdea;
use App\Models\Task;
use App\Models\TaskSchedule;
use App\Models\WritingRule;
use App\Models\WritingRuleVersion;
use App\Services\GeoFlow\ContentProductionScheduleService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ContentProductionAutomationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config()->set('geoflow.content_production_pipeline_enabled', true);
    }

    public function test_due_scheduler_creates_one_daily_occurrence_without_duplicates(): void
    {
        Queue::fake();
        [$admin, $rule, $version] = $this->writingRule();
        $clock = Carbon::parse('2026-08-18 09:00:00', 'Asia/Shanghai')->utc();
        $task = $this->task($admin, $rule, $version, $clock);

        $first = app(ContentProductionScheduleService::class)->dispatchDue($clock);
        $second = app(ContentProductionScheduleService::class)->dispatchDue($clock);

        $this->assertSame(['queued' => 1, 'skipped' => 0], $first);
        $this->assertSame(['queued' => 0, 'skipped' => 0], $second);
        $this->assertSame(1, TaskSchedule::query()->where('task_id', $task->id)->count());
        $schedule = TaskSchedule::query()->where('task_id', $task->id)->firstOrFail();
        $this->assertSame('第一个中文选题', $schedule->topic);
        $this->assertSame('2026-08-18', $schedule->local_date->toDateString());
    }

    public function test_admin_update_fixes_rule_version_and_can_fallback_to_legacy(): void
    {
        [$admin, $rule, $version] = $this->writingRule();
        $task = Task::query()->create(['name' => '待配置自动任务', 'status' => 'active']);

        $this->actingAs($admin, 'admin')->put(route('admin.content-automations.update', $task), [
            'writing_rule_id' => $rule->id,
            'writing_rule_version_id' => $version->id,
            'topics' => "第一个中文选题\n第二个中文选题\n第一个中文选题",
            'automation_timezone' => 'Asia/Shanghai',
            'daily_production_limit' => 2,
            'max_production_concurrency' => 1,
            'production_failure_policy' => 'continue',
            'daily_token_budget' => 50000,
        ])->assertRedirect(route('admin.content-automations.index'));

        $task->refresh();
        $this->assertSame(TaskPipelineMode::ContentProduction, $task->pipeline_mode);
        $this->assertSame($admin->id, $task->created_by_admin_id);
        $this->assertSame($version->id, $task->writing_rule_version_id);
        $this->assertSame(['第一个中文选题', '第二个中文选题'], data_get($task->automation_settings, 'topics'));
        $this->assertSame(['wordpress'], data_get($task->automation_settings, 'target_platforms'));
        $this->assertFalse($task->auto_publish_enabled);

        $this->post(route('admin.content-automations.fallback', $task))->assertRedirect();
        $this->assertSame(TaskPipelineMode::Legacy, $task->fresh()->pipeline_mode);
        $this->assertSame(0, (int) $task->fresh()->schedule_enabled);
    }

    public function test_production_plan_consumes_one_topic_idea_and_records_its_snapshot_reference(): void
    {
        Queue::fake();
        [$admin, $rule, $version] = $this->writingRule();
        $clock = Carbon::parse('2026-08-18 09:00:00', 'Asia/Shanghai')->utc();
        $topic = ContentTopic::query()->create([
            'created_by_admin_id' => $admin->id,
            'writing_rule_id' => $rule->id,
            'name' => '企业视频会议内容专题',
            'is_active' => true,
        ]);
        $idea = ContentTopicIdea::query()->create([
            'content_topic_id' => $topic->id,
            'topic' => '企业视频会议如何选择',
            'status' => 'candidate',
        ]);
        $task = $this->task($admin, $rule, $version, $clock);
        $task->update(['content_topic_id' => $topic->id, 'production_time' => '09:30']);

        $schedule = app(ContentProductionScheduleService::class)->createOccurrence($task->id, $clock);

        $this->assertNotNull($schedule);
        $this->assertSame($idea->topic, $schedule->topic);
        $this->assertSame($idea->id, (int) data_get($schedule->metadata, 'content_topic_idea_id'));
        $this->assertSame($topic->id, (int) data_get($schedule->metadata, 'content_topic_id'));
        $this->assertSame('scheduled', $idea->fresh()->status);
    }

    public function test_normal_admin_cannot_manage_content_automation(): void
    {
        $admin = $this->admin('normal-automation-admin', 'admin');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.content-automations.index'))
            ->assertForbidden();
    }

    public function test_failed_occurrence_can_be_queued_for_manual_resume(): void
    {
        Queue::fake();
        [$admin, $rule, $version] = $this->writingRule();
        $task = $this->task($admin, $rule, $version, now()->utc());
        $task->forceFill(['schedule_enabled' => 0])->save();
        $schedule = TaskSchedule::query()->create([
            'task_id' => $task->id,
            'next_run_time' => now(),
            'local_date' => now()->toDateString(),
            'slot' => 1,
            'topic' => '需要续跑的中文选题',
            'topic_hash' => hash('sha256', '需要续跑的中文选题'),
            'status' => TaskScheduleStatus::Failed,
            'attempt_count' => 1,
            'error_message' => '章节生成失败',
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.content-automations.retry', [$task, $schedule]))
            ->assertRedirect();

        $schedule->refresh();
        $this->assertSame(TaskScheduleStatus::Pending, $schedule->status);
        $this->assertTrue((bool) data_get($schedule->metadata, 'manual_retry'));
        Queue::assertPushed(ProcessStandardContentProductionJob::class, fn ($job) => $job->taskScheduleId === $schedule->id);
    }

    public function test_retry_rejects_schedule_from_another_task(): void
    {
        Queue::fake();
        [$admin, $rule, $version] = $this->writingRule();
        $task = $this->task($admin, $rule, $version, now()->utc());
        $other = $this->task($admin, $rule, $version, now()->addMinute()->utc());
        $schedule = TaskSchedule::query()->create([
            'task_id' => $other->id,
            'next_run_time' => now(),
            'local_date' => now()->toDateString(),
            'slot' => 1,
            'topic' => '其他任务的选题',
            'topic_hash' => hash('sha256', '其他任务的选题'),
            'status' => TaskScheduleStatus::Failed,
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.content-automations.retry', [$task, $schedule]))
            ->assertNotFound();
        Queue::assertNothingPushed();
    }

    public function test_automation_index_displays_simple_run_statistics(): void
    {
        [$admin, $rule, $version] = $this->writingRule();
        $task = $this->task($admin, $rule, $version, now()->utc());
        foreach ([['completed', 12000], ['failed', 5000]] as $index => [$status, $duration]) {
            $schedule = TaskSchedule::query()->create([
                'task_id' => $task->id,
                'next_run_time' => now()->addMinutes($index),
                'local_date' => now()->toDateString(),
                'slot' => $index + 1,
                'topic' => '统计选题'.$index,
                'topic_hash' => hash('sha256', '统计选题'.$index),
                'status' => $status,
            ]);
            ContentAutomationRun::query()->create([
                'task_id' => $task->id,
                'task_schedule_id' => $schedule->id,
                'status' => $status,
                'duration_ms' => $duration,
            ]);
        }

        $this->actingAs($admin, 'admin')
            ->get(route('admin.content-automations.index'))
            ->assertOk()
            ->assertSee('成功 1')
            ->assertSee('失败 1')
            ->assertSee('成功率 50%')
            ->assertSee('平均耗时 12 秒');
    }

    /** @return array{Admin, WritingRule, WritingRuleVersion} */
    private function writingRule(): array
    {
        $admin = $this->admin('automation-super-admin', 'super_admin');
        $rule = WritingRule::query()->create([
            'created_by_admin_id' => $admin->id,
            'name' => '每日中文文章规则',
            'description' => '用于标准自动内容生产',
            'is_active' => true,
            'current_version' => 1,
        ]);
        $settings = ['language' => 'zh_CN', 'target_platforms' => ['wordpress']];
        $version = WritingRuleVersion::query()->create([
            'writing_rule_id' => $rule->id,
            'created_by_admin_id' => $admin->id,
            'version' => 1,
            'settings' => $settings,
            'settings_hash' => hash('sha256', json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
        ]);

        return [$admin, $rule, $version];
    }

    private function task(Admin $admin, WritingRule $rule, WritingRuleVersion $version, Carbon $clock): Task
    {
        return Task::query()->create([
            'name' => '每日标准内容生产',
            'status' => 'active',
            'schedule_enabled' => 1,
            'next_run_at' => $clock,
            'created_by_admin_id' => $admin->id,
            'pipeline_mode' => TaskPipelineMode::ContentProduction,
            'writing_rule_id' => $rule->id,
            'writing_rule_version_id' => $version->id,
            'automation_timezone' => 'Asia/Shanghai',
            'daily_production_limit' => 1,
            'max_production_concurrency' => 1,
            'production_failure_policy' => 'continue',
            'automation_settings' => ['topics' => ['第一个中文选题'], 'target_platforms' => ['wordpress']],
        ]);
    }

    private function admin(string $username, string $role): Admin
    {
        return Admin::query()->create([
            'username' => $username,
            'password' => 'secret-123',
            'email' => $username.'@example.com',
            'display_name' => 'Automation Admin',
            'role' => $role,
            'status' => 'active',
        ]);
    }
}
