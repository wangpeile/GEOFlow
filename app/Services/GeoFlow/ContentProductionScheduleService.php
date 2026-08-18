<?php

namespace App\Services\GeoFlow;

use App\Enums\TaskPipelineMode;
use App\Enums\TaskScheduleStatus;
use App\Jobs\ProcessStandardContentProductionJob;
use App\Models\ContentAutomationRun;
use App\Models\Task;
use App\Models\TaskSchedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ContentProductionScheduleService
{
    /**
     * @return array{queued:int,skipped:int}
     */
    public function dispatchDue(?Carbon $clock = null): array
    {
        $clock ??= now();
        $queued = 0;
        $skipped = 0;

        Task::query()
            ->select(['id'])
            ->where('pipeline_mode', TaskPipelineMode::ContentProduction->value)
            ->where('status', 'active')
            ->where('schedule_enabled', 1)
            ->whereNotNull('created_by_admin_id')
            ->whereNotNull('writing_rule_id')
            ->whereNotNull('writing_rule_version_id')
            ->where(function ($query) use ($clock): void {
                $query->whereNull('next_run_at')->orWhere('next_run_at', '<=', $clock);
            })
            ->orderBy('id')
            ->chunkById(100, function ($tasks) use ($clock, &$queued, &$skipped): void {
                foreach ($tasks as $taskReference) {
                    $schedule = $this->createOccurrence((int) $taskReference->id, $clock);
                    if (! $schedule) {
                        $skipped++;

                        continue;
                    }

                    ProcessStandardContentProductionJob::dispatch($schedule->id)->afterCommit();
                    $queued++;
                }
            });

        return ['queued' => $queued, 'skipped' => $skipped];
    }

    public function createOccurrence(int $taskId, Carbon $clock, bool $force = false): ?TaskSchedule
    {
        return DB::transaction(function () use ($taskId, $clock, $force): ?TaskSchedule {
            $task = Task::query()->lockForUpdate()->findOrFail($taskId);
            if ($task->pipeline_mode !== TaskPipelineMode::ContentProduction || $task->status !== 'active') {
                return null;
            }

            $timezone = $task->automation_timezone ?: 'Asia/Shanghai';
            $localDate = $clock->copy()->timezone($timezone)->toDateString();
            $dailyCount = TaskSchedule::query()
                ->whereBelongsTo($task)
                ->where('local_date', $localDate)
                ->whereNull('cancelled_at')
                ->count();
            $runningCount = TaskSchedule::query()
                ->whereBelongsTo($task)
                ->where('status', TaskScheduleStatus::Running->value)
                ->count();
            $usedTokens = ContentAutomationRun::query()
                ->whereBelongsTo($task)
                ->whereHas('schedule', fn ($query) => $query->where('local_date', $localDate))
                ->sum('token_usage');

            if (! $force && $runningCount >= max(1, $task->max_production_concurrency)) {
                $this->advanceTask($task, $clock, $timezone, false);

                return null;
            }
            if (! $force && ($dailyCount >= max(1, $task->daily_production_limit)
                || ($task->daily_token_budget && $usedTokens >= $task->daily_token_budget))) {
                $this->advanceTask($task, $clock, $timezone, true);

                return null;
            }

            $topics = collect(data_get($task->automation_settings, 'topics', []))
                ->map(fn (mixed $topic): string => Str::squish((string) $topic))
                ->filter()
                ->unique()
                ->values();
            if ($topics->isEmpty()) {
                $task->forceFill([
                    'last_error_at' => $clock,
                    'last_error_message' => '标准自动模式没有可用选题。',
                ])->save();

                return null;
            }

            $historicalCount = TaskSchedule::query()->whereBelongsTo($task)->whereNotNull('topic_hash')->count();
            for ($offset = 0; $offset < $topics->count(); $offset++) {
                $topic = $topics[($historicalCount + $offset) % $topics->count()];
                $topicHash = hash('sha256', Str::lower($topic));
                if (TaskSchedule::query()->whereBelongsTo($task)->where('local_date', $localDate)->where('topic_hash', $topicHash)->exists()) {
                    continue;
                }

                $schedule = TaskSchedule::query()->create([
                    'task_id' => $task->id,
                    'next_run_time' => $clock,
                    'local_date' => $localDate,
                    'slot' => $dailyCount + 1,
                    'topic' => $topic,
                    'topic_hash' => $topicHash,
                    'status' => TaskScheduleStatus::Pending,
                    'metadata' => ['timezone' => $timezone, 'forced' => $force],
                ]);
                $this->advanceTask($task, $clock, $timezone, $dailyCount + 1 >= max(1, $task->daily_production_limit));

                return $schedule;
            }

            $this->advanceTask($task, $clock, $timezone, true);

            return null;
        }, 3);
    }

    private function advanceTask(Task $task, Carbon $clock, string $timezone, bool $nextDay): void
    {
        $nextRunAt = $nextDay
            ? $clock->copy()->timezone($timezone)->addDay()->startOfDay()->addMinutes(5)->utc()
            : $clock->copy()->addMinute();
        $task->forceFill(['next_run_at' => $nextRunAt, 'last_run_at' => $clock])->save();
    }
}
