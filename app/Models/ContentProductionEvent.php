<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentProductionEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'content_production_id',
        'content_stage_run_id',
        'admin_id',
        'event',
        'from_status',
        'to_status',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'content_production_id' => 'integer',
            'content_stage_run_id' => 'integer',
            'admin_id' => 'integer',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function contentProduction(): BelongsTo
    {
        return $this->belongsTo(ContentProduction::class);
    }

    public function stageRun(): BelongsTo
    {
        return $this->belongsTo(ContentStageRun::class, 'content_stage_run_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }
}
