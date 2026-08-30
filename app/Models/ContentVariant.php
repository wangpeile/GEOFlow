<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ContentVariant extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_READY = 'ready';

    public const STATUS_GENERATING = 'generating';

    public const STATUS_REVIEW_PENDING = 'review_pending';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_FAILED = 'failed';

    public const REVIEW_PENDING = 'pending';

    public const REVIEW_APPROVED = 'approved';

    public const REVIEW_REJECTED = 'rejected';

    protected $fillable = [
        'content_group_id',
        'source_article_id',
        'platform',
        'content_platform_specification_id',
        'title',
        'excerpt',
        'content',
        'tags',
        'image_requirements',
        'status',
        'review_status',
        'reviewed_by',
        'reviewed_at',
        'review_note',
        'version',
        'template_version',
        'platform_specification_version',
        'generation_meta',
        'generation_token',
        'generation_started_at',
        'source_content_hash',
        'fact_check',
        'quality_check',
        'publication_payload',
        'publication_readiness',
        'failure_message',
        'published_url',
        'published_at',
    ];

    protected $attributes = [
        'title' => '',
        'status' => self::STATUS_PENDING,
        'review_status' => self::REVIEW_PENDING,
        'version' => 1,
        'template_version' => '1.0',
    ];

    protected function casts(): array
    {
        return [
            'content_group_id' => 'integer',
            'source_article_id' => 'integer',
            'version' => 'integer',
            'tags' => 'array',
            'image_requirements' => 'array',
            'generation_meta' => 'array',
            'fact_check' => 'array',
            'quality_check' => 'array',
            'publication_payload' => 'array',
            'publication_readiness' => 'array',
            'reviewed_by' => 'integer',
            'reviewed_at' => 'datetime',
            'generation_started_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function contentGroup(): BelongsTo
    {
        return $this->belongsTo(ContentGroup::class);
    }

    public function sourceArticle(): BelongsTo
    {
        return $this->belongsTo(Article::class, 'source_article_id')->withTrashed();
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ContentVariantVersion::class)->orderByDesc('version');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ContentVariantReview::class)->orderByDesc('id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by');
    }

    public function latestPublication(): HasOne
    {
        return $this->hasOne(ArticleDistribution::class)->latestOfMany();
    }

    public function platformSpecification(): BelongsTo
    {
        return $this->belongsTo(ContentPlatformSpecification::class);
    }

    public function platformFeedback(): HasMany
    {
        return $this->hasMany(ContentPlatformFeedback::class)->latest();
    }
}
