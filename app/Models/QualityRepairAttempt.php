<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QualityRepairAttempt extends Model
{
    protected $fillable = [
        'content_production_id',
        'quality_report_id',
        'source_article_version_id',
        'repaired_article_version_id',
        'attempt',
        'status',
        'selected_issue_ids',
        'result',
        'error_message',
        'created_by_admin_id',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'content_production_id' => 'integer',
            'quality_report_id' => 'integer',
            'source_article_version_id' => 'integer',
            'repaired_article_version_id' => 'integer',
            'attempt' => 'integer',
            'selected_issue_ids' => 'array',
            'result' => 'array',
            'created_by_admin_id' => 'integer',
            'finished_at' => 'datetime',
        ];
    }

    public function contentProduction(): BelongsTo
    {
        return $this->belongsTo(ContentProduction::class);
    }

    public function qualityReport(): BelongsTo
    {
        return $this->belongsTo(QualityReport::class);
    }

    public function sourceArticleVersion(): BelongsTo
    {
        return $this->belongsTo(ArticleVersion::class, 'source_article_version_id');
    }

    public function repairedArticleVersion(): BelongsTo
    {
        return $this->belongsTo(ArticleVersion::class, 'repaired_article_version_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }
}
