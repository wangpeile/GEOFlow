<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ArticleDistribution extends Model
{
    protected $fillable = [
        'article_id',
        'content_group_id',
        'content_variant_id',
        'content_variant_version_id',
        'reviewed_by_admin_id',
        'distribution_channel_id',
        'action',
        'publication_mode',
        'scheduled_for',
        'published_version',
        'published_at',
        'status',
        'remote_id',
        'remote_url',
        'remote_meta',
        'idempotency_key',
        'attempt_count',
        'next_retry_at',
        'last_attempt_at',
        'last_error_message',
        'payload_hash',
    ];

    protected function casts(): array
    {
        return [
            'article_id' => 'integer',
            'content_group_id' => 'integer',
            'content_variant_id' => 'integer',
            'content_variant_version_id' => 'integer',
            'reviewed_by_admin_id' => 'integer',
            'distribution_channel_id' => 'integer',
            'attempt_count' => 'integer',
            'next_retry_at' => 'datetime',
            'last_attempt_at' => 'datetime',
            'scheduled_for' => 'datetime',
            'published_version' => 'integer',
            'published_at' => 'datetime',
            'remote_meta' => 'array',
        ];
    }

    public function wordpressPostId(): ?int
    {
        if ($this->remote_id !== null && ctype_digit((string) $this->remote_id)) {
            return (int) $this->remote_id;
        }

        $meta = is_array($this->remote_meta) ? $this->remote_meta : [];
        $postId = $meta['wordpress_post_id'] ?? null;

        return is_numeric($postId) ? (int) $postId : null;
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class, 'article_id');
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(DistributionChannel::class, 'distribution_channel_id');
    }

    public function contentGroup(): BelongsTo
    {
        return $this->belongsTo(ContentGroup::class);
    }

    public function contentVariant(): BelongsTo
    {
        return $this->belongsTo(ContentVariant::class);
    }

    public function contentVariantVersion(): BelongsTo
    {
        return $this->belongsTo(ContentVariantVersion::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by_admin_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(DistributionLog::class, 'article_distribution_id');
    }
}
