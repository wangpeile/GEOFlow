<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentVariant extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_READY = 'ready';

    public const REVIEW_PENDING = 'pending';

    protected $fillable = [
        'content_group_id',
        'source_article_id',
        'platform',
        'title',
        'excerpt',
        'content',
        'status',
        'review_status',
        'version',
        'template_version',
        'generation_meta',
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
            'generation_meta' => 'array',
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
}
