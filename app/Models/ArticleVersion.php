<?php

namespace App\Models;

use App\Enums\ArticleVersionKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ArticleVersion extends Model
{
    protected $fillable = [
        'content_production_id',
        'article_id',
        'version',
        'kind',
        'title',
        'summary',
        'body',
        'faq',
        'meta_title',
        'meta_description',
        'section_version_ids',
        'input_hash',
        'created_by_admin_id',
        'synchronized_at',
    ];

    protected function casts(): array
    {
        return [
            'content_production_id' => 'integer',
            'article_id' => 'integer',
            'version' => 'integer',
            'kind' => ArticleVersionKind::class,
            'faq' => 'array',
            'section_version_ids' => 'array',
            'created_by_admin_id' => 'integer',
            'synchronized_at' => 'datetime',
        ];
    }

    public function contentProduction(): BelongsTo
    {
        return $this->belongsTo(ContentProduction::class);
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    public function qualityReports(): HasMany
    {
        return $this->hasMany(QualityReport::class);
    }
}
