<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TaskPipelineMode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateTaskAutomationRequest;
use App\Jobs\ProcessStandardContentProductionJob;
use App\Models\Task;
use App\Models\TaskSchedule;
use App\Models\WritingRule;
use App\Services\GeoFlow\ContentProductionScheduleService;
use App\Support\AdminWeb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TaskAutomationController extends Controller
{
    public function index(): View
    {
        $tasks = Task::query()
            ->select(['id', 'name', 'status', 'pipeline_mode', 'schedule_enabled', 'daily_production_limit', 'next_run_at', 'last_success_at', 'last_error_message'])
            ->withCount([
                'taskSchedules',
                'contentProductions',
                'automationRuns as successful_runs_count' => fn ($query) => $query->where('status', 'completed'),
                'automationRuns as failed_runs_count' => fn ($query) => $query->where('status', 'failed'),
            ])
            ->withAvg(['automationRuns as average_duration_ms' => fn ($query) => $query->where('status', 'completed')], 'duration_ms')
            ->latest()
            ->paginate((int) config('geoflow.admin_items_per_page', 20));

        return view('admin.content-productions.automation-index', $this->viewData(['tasks' => $tasks]));
    }

    public function edit(Task $task): View
    {
        $task->load([
            'writingRule.versions:id,writing_rule_id,version',
            'knowledgeBases:id,name',
            'knowledgeBase:id,name',
            'taskSchedules' => fn ($query) => $query->with('contentProduction:id,name')->latest('id')->limit(10),
        ]);

        return view('admin.content-productions.automation-edit', $this->viewData([
            'task' => $task,
            'writingRules' => WritingRule::query()->where('is_active', true)->with('versions:id,writing_rule_id,version')->orderBy('name')->get(),
        ]));
    }

    public function update(UpdateTaskAutomationRequest $request, Task $task): RedirectResponse
    {
        $data = $request->validated();
        $topics = collect(preg_split('/\R/u', $data['topics']) ?: [])
            ->map(fn (string $topic): string => trim($topic))
            ->filter()
            ->unique()
            ->values()
            ->all();
        $task->forceFill([
            'created_by_admin_id' => Auth::guard('admin')->id(),
            'pipeline_mode' => TaskPipelineMode::ContentProduction,
            'writing_rule_id' => $data['writing_rule_id'],
            'writing_rule_version_id' => $data['writing_rule_version_id'],
            'automation_timezone' => $data['automation_timezone'],
            'daily_production_limit' => $data['daily_production_limit'],
            'max_production_concurrency' => $data['max_production_concurrency'],
            'production_failure_policy' => $data['production_failure_policy'],
            'production_output_policy' => 'wordpress_draft',
            'auto_publish_enabled' => false,
            'daily_token_budget' => $data['daily_token_budget'] ?? null,
            'automation_settings' => ['topics' => $topics, 'target_platforms' => ['wordpress']],
            'schedule_enabled' => 1,
            'next_run_at' => now(),
            'last_error_message' => null,
        ])->save();

        return redirect()->route('admin.content-automations.index')->with('message', '每日内容生产计划已保存并启用。');
    }

    public function pause(Task $task): RedirectResponse
    {
        $task->forceFill(['schedule_enabled' => 0])->save();

        return back()->with('message', '计划已暂停。');
    }

    public function resume(Task $task): RedirectResponse
    {
        $task->forceFill(['schedule_enabled' => 1, 'next_run_at' => now()])->save();

        return back()->with('message', '计划已恢复。');
    }

    public function fallback(Task $task): RedirectResponse
    {
        $task->forceFill(['pipeline_mode' => TaskPipelineMode::Legacy, 'schedule_enabled' => 0])->save();

        return back()->with('message', '已回退旧版任务模式，标准内容生产计划已停止。');
    }

    public function runNow(Task $task, ContentProductionScheduleService $scheduler): RedirectResponse
    {
        $schedule = $scheduler->createOccurrence($task->id, now(), true);
        if ($schedule) {
            ProcessStandardContentProductionJob::dispatch($schedule->id)->afterCommit();
        }

        return back()->with('message', $schedule ? '已创建一次立即执行任务。' : '没有可执行的新选题。');
    }

    public function retry(Task $task, TaskSchedule $taskSchedule): RedirectResponse
    {
        abort_unless($taskSchedule->task_id === $task->id, 404);

        $queued = DB::transaction(function () use ($taskSchedule): bool {
            $schedule = TaskSchedule::query()->lockForUpdate()->findOrFail($taskSchedule->id);
            if ($schedule->status->value !== 'failed') {
                return false;
            }
            $metadata = $schedule->metadata ?? [];
            $metadata['manual_retry'] = true;
            $metadata['retried_at'] = now()->toIso8601String();
            $schedule->forceFill([
                'status' => 'pending',
                'error_message' => null,
                'finished_at' => null,
                'metadata' => $metadata,
            ])->save();

            return true;
        });

        if ($queued) {
            ProcessStandardContentProductionJob::dispatch($taskSchedule->id)->afterCommit();
        }

        return back()->with('message', $queued ? '失败任务已重新加入队列，将从已有成果处继续。' : '该任务当前不能重试。');
    }

    private function viewData(array $data): array
    {
        return array_merge(['pageTitle' => '每日内容生产', 'activeMenu' => 'articles', 'adminSiteName' => AdminWeb::siteName()], $data);
    }
}
