<?php

namespace App\Models;

use App\Enums\QualityReportStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QualityReport extends Model
{
    protected $fillable = [
        'content_production_id',
        'article_version_id',
        'version',
        'status',
        'issues',
        'summary',
        'input_hash',
        'ruleset_version',
        'risk_snapshot',
        'created_by_admin_id',
        'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'content_production_id' => 'integer',
            'article_version_id' => 'integer',
            'version' => 'integer',
            'status' => QualityReportStatus::class,
            'issues' => 'array',
            'summary' => 'array',
            'risk_snapshot' => 'array',
            'created_by_admin_id' => 'integer',
            'checked_at' => 'datetime',
        ];
    }

    public function contentProduction(): BelongsTo
    {
        return $this->belongsTo(ContentProduction::class);
    }

    public function articleVersion(): BelongsTo
    {
        return $this->belongsTo(ArticleVersion::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    public function repairAttempts(): HasMany
    {
        return $this->hasMany(QualityRepairAttempt::class);
    }
}
