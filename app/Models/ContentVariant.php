<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContentVariant extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_READY = 'ready';

    public const STATUS_GENERATING = 'generating';

    public const STATUS_REVIEW_PENDING = 'review_pending';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_FAILED = 'failed';

    public const REVIEW_PENDING = 'pending';

    protected $fillable = [
        'content_group_id',
        'source_article_id',
        'platform',
        'title',
        'excerpt',
        'content',
        'tags',
        'image_requirements',
        'status',
        'review_status',
        'version',
        'template_version',
        'generation_meta',
        'generation_token',
        'generation_started_at',
        'source_content_hash',
        'fact_check',
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
}
