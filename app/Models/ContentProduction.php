<?php

namespace App\Models;

use App\Enums\ContentProductionMode;
use App\Enums\ContentProductionStage;
use App\Enums\ContentProductionStatus;
use App\Enums\ContentStageFailureType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContentProduction extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'idempotency_key',
        'task_id',
        'article_id',
        'created_by_admin_id',
        'writing_rule_id',
        'writing_rule_version_id',
        'name',
        'topic',
        'mode',
        'status',
        'current_stage',
        'language',
        'target_platforms',
        'writing_rule_snapshot',
        'context',
        'failure_type',
        'last_error_message',
        'started_at',
        'completed_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'task_id' => 'integer',
            'article_id' => 'integer',
            'created_by_admin_id' => 'integer',
            'writing_rule_id' => 'integer',
            'writing_rule_version_id' => 'integer',
            'mode' => ContentProductionMode::class,
            'status' => ContentProductionStatus::class,
            'current_stage' => ContentProductionStage::class,
            'target_platforms' => 'array',
            'writing_rule_snapshot' => 'array',
            'context' => 'array',
            'failure_type' => ContentStageFailureType::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    public function writingRule(): BelongsTo
    {
        return $this->belongsTo(WritingRule::class);
    }

    public function writingRuleVersion(): BelongsTo
    {
        return $this->belongsTo(WritingRuleVersion::class);
    }

    public function stageRuns(): HasMany
    {
        return $this->hasMany(ContentStageRun::class)->orderBy('sequence')->orderBy('attempt');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ContentProductionEvent::class)->latest('created_at')->latest('id');
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(ContentEvidence::class)->latest('created_at')->latest('id');
    }

    public function directionVersions(): HasMany
    {
        return $this->hasMany(ContentDirectionVersion::class)->latest('version');
    }

    public function sectionVersions(): HasMany
    {
        return $this->hasMany(ContentSectionVersion::class)
            ->orderBy('position')
            ->orderByDesc('version');
    }

    public function articleVersions(): HasMany
    {
        return $this->hasMany(ArticleVersion::class)->latest('version');
    }

    public function qualityReports(): HasMany
    {
        return $this->hasMany(QualityReport::class)->latest('version');
    }

    public function qualityRepairAttempts(): HasMany
    {
        return $this->hasMany(QualityRepairAttempt::class)->latest('attempt');
    }
}
