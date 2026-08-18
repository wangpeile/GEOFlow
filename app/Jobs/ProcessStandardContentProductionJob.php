<?php

namespace App\Jobs;

use App\Enums\TaskScheduleStatus;
use App\Models\ContentAutomationRun;
use App\Models\TaskSchedule;
use App\Services\GeoFlow\StandardContentProductionRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessStandardContentProductionJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public int $uniqueFor = 600;

    public function __construct(public readonly int $taskScheduleId)
    {
        $this->onQueue('geoflow');
    }

    public function uniqueId(): string
    {
        return (string) $this->taskScheduleId;
    }

    public function handle(StandardContentProductionRunner $runner): void
    {
        $claimed = DB::transaction(function (): ?TaskSchedule {
            $schedule = TaskSchedule::query()->with('task')->lockForUpdate()->find($this->taskScheduleId);
            if (! $schedule || ! in_array($schedule->status, [TaskScheduleStatus::Pending, TaskScheduleStatus::Failed], true)) {
                return null;
            }
            $manualRetry = (bool) data_get($schedule->metadata, 'manual_retry', false);
            if ($schedule->task->status !== 'active' || ((int) $schedule->task->schedule_enabled !== 1 && ! $manualRetry)) {
                $schedule->forceFill(['status' => TaskScheduleStatus::Cancelled, 'cancelled_at' => now()])->save();

                return null;
            }

            $schedule->forceFill([
                'status' => TaskScheduleStatus::Running,
                'attempt_count' => $schedule->attempt_count + 1,
                'started_at' => now(),
                'error_message' => null,
            ])->save();
            ContentAutomationRun::query()->updateOrCreate(
                ['task_schedule_id' => $schedule->id],
                ['task_id' => $schedule->task_id, 'status' => 'running', 'attempt' => $schedule->attempt_count, 'started_at' => now()]
            );

            return $schedule;
        });
        if (! $claimed) {
            return;
        }

        $started = hrtime(true);
        try {
            $production = $runner->run($claimed);
            $durationMs = (int) ((hrtime(true) - $started) / 1_000_000);
            DB::transaction(function () use ($production, $durationMs): void {
                $schedule = TaskSchedule::query()->lockForUpdate()->findOrFail($this->taskScheduleId);
                $schedule->forceFill([
                    'content_production_id' => $production->id,
                    'status' => TaskScheduleStatus::Completed,
                    'finished_at' => now(),
                    'error_message' => null,
                ])->save();
                ContentAutomationRun::query()->where('task_schedule_id', $schedule->id)->update([
                    'content_production_id' => $production->id,
                    'status' => 'completed',
                    'duration_ms' => $durationMs,
                    'finished_at' => now(),
                    'updated_at' => now(),
                ]);
                $schedule->task()->update(['last_success_at' => now(), 'last_error_message' => null]);
            });
        } catch (Throwable $exception) {
            $this->markFailed($exception, (int) ((hrtime(true) - $started) / 1_000_000));
        }
    }

    public function failed(?Throwable $exception): void
    {
        if ($exception) {
            $this->markFailed($exception);
        }
    }

    private function markFailed(Throwable $exception, ?int $durationMs = null): void
    {
        $message = mb_substr($exception->getMessage(), 0, 2000);
        DB::transaction(function () use ($message): void {
            $schedule = TaskSchedule::query()->with('task')->lockForUpdate()->find($this->taskScheduleId);
            if (! $schedule || $schedule->status === TaskScheduleStatus::Completed) {
                return;
            }
            $schedule->forceFill(['status' => TaskScheduleStatus::Failed, 'error_message' => $message, 'finished_at' => now()])->save();
            $measuredDuration = $durationMs ?? max(0, (int) ($schedule->started_at?->diffInMilliseconds(now()) ?? 0));
            ContentAutomationRun::query()->where('task_schedule_id', $schedule->id)->update([
                'status' => 'failed', 'error_message' => $message, 'duration_ms' => $measuredDuration,
                'finished_at' => now(), 'updated_at' => now(),
            ]);
            $updates = ['last_error_at' => now(), 'last_error_message' => $message];
            if ($schedule->task->production_failure_policy === 'pause') {
                $updates['schedule_enabled'] = 0;
            }
            $schedule->task()->update($updates);
        });
        Log::warning('Standard content production failed.', ['task_schedule_id' => $this->taskScheduleId, 'error' => $message]);
    }
}
