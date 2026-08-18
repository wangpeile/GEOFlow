<?php

namespace App\Models;

use App\Enums\TaskScheduleStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class TaskSchedule extends Model
{
    protected $table = 'task_schedules';

    protected $fillable = [
        'task_id',
        'uuid',
        'next_run_time',
        'local_date',
        'slot',
        'topic',
        'topic_hash',
        'content_production_id',
        'status',
        'error_message',
        'attempt_count',
        'started_at',
        'finished_at',
        'cancelled_at',
        'metadata',
    ];

    protected $attributes = [
        'status' => 'pending',
        'attempt_count' => 0,
    ];

    protected static function booted(): void
    {
        static::creating(function (self $schedule): void {
            $schedule->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'task_id' => 'integer',
            'slot' => 'integer',
            'content_production_id' => 'integer',
            'status' => TaskScheduleStatus::class,
            'attempt_count' => 'integer',
            'next_run_time' => 'datetime',
            'local_date' => 'date',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    public function contentProduction(): BelongsTo
    {
        return $this->belongsTo(ContentProduction::class);
    }

    public function automationRun(): HasOne
    {
        return $this->hasOne(ContentAutomationRun::class);
    }
}
