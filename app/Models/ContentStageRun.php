<?php

namespace App\Models;

use App\Enums\ContentProductionStage;
use App\Enums\ContentStageFailureType;
use App\Enums\ContentStageStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentStageRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'content_production_id',
        'stage',
        'status',
        'sequence',
        'attempt',
        'contract_version',
        'input_hash',
        'input_payload',
        'output_payload',
        'model',
        'rule_version',
        'failure_type',
        'error_message',
        'duration_ms',
        'started_at',
        'finished_at',
        'invalidated_at',
        'invalidation_reason',
    ];

    protected function casts(): array
    {
        return [
            'content_production_id' => 'integer',
            'stage' => ContentProductionStage::class,
            'status' => ContentStageStatus::class,
            'sequence' => 'integer',
            'attempt' => 'integer',
            'contract_version' => 'integer',
            'input_payload' => 'array',
            'output_payload' => 'array',
            'failure_type' => ContentStageFailureType::class,
            'duration_ms' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'invalidated_at' => 'datetime',
        ];
    }

    public function contentProduction(): BelongsTo
    {
        return $this->belongsTo(ContentProduction::class);
    }
}
