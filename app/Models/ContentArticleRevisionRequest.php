<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentArticleRevisionRequest extends Model
{
    protected $fillable = [
        'content_production_id', 'source_article_version_id', 'revised_article_version_id',
        'quality_report_id', 'created_by_admin_id', 'feedback', 'instruction_snapshot',
        'model', 'prompt_version', 'input_hash', 'status', 'result', 'error_message',
        'started_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'content_production_id' => 'integer', 'source_article_version_id' => 'integer',
            'revised_article_version_id' => 'integer', 'quality_report_id' => 'integer',
            'created_by_admin_id' => 'integer', 'instruction_snapshot' => 'array', 'result' => 'array',
            'started_at' => 'datetime', 'finished_at' => 'datetime',
        ];
    }

    public function contentProduction(): BelongsTo { return $this->belongsTo(ContentProduction::class); }
    public function sourceArticleVersion(): BelongsTo { return $this->belongsTo(ArticleVersion::class, 'source_article_version_id'); }
    public function revisedArticleVersion(): BelongsTo { return $this->belongsTo(ArticleVersion::class, 'revised_article_version_id'); }
    public function qualityReport(): BelongsTo { return $this->belongsTo(QualityReport::class); }
    public function createdBy(): BelongsTo { return $this->belongsTo(Admin::class, 'created_by_admin_id'); }
}
