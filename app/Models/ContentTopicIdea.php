<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentTopicIdea extends Model
{
    use HasFactory;

    public const STATUSES = ['candidate', 'scheduled', 'generating', 'published', 'needs_update', 'disabled'];

    protected $fillable = ['content_topic_id', 'topic', 'keywords', 'angle', 'status', 'scheduled_for', 'notes'];

    protected function casts(): array
    {
        return [
            'content_topic_id' => 'integer',
            'keywords' => 'array',
            'scheduled_for' => 'datetime',
        ];
    }

    public function contentTopic(): BelongsTo
    {
        return $this->belongsTo(ContentTopic::class);
    }
}
