<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentAutomationRun extends Model
{
    protected $fillable = [
        'task_id', 'task_schedule_id', 'content_production_id', 'status', 'attempt',
        'token_usage', 'duration_ms', 'error_message', 'started_at', 'finished_at', 'metadata',
    ];

    protected $attributes = [
        'status' => 'pending',
        'attempt' => 1,
        'token_usage' => 0,
        'duration_ms' => 0,
    ];

    protected function casts(): array
    {
        return [
            'task_id' => 'integer',
            'task_schedule_id' => 'integer',
            'content_production_id' => 'integer',
            'attempt' => 'integer',
            'token_usage' => 'integer',
            'duration_ms' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(TaskSchedule::class, 'task_schedule_id');
    }

    public function production(): BelongsTo
    {
        return $this->belongsTo(ContentProduction::class, 'content_production_id');
    }
}
