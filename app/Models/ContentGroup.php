<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContentGroup extends Model
{
    public const STATUS_DRAFT = 'draft';

    protected $fillable = [
        'main_article_id',
        'task_id',
        'name',
        'status',
        'generation_meta',
    ];

    protected $attributes = [
        'status' => self::STATUS_DRAFT,
    ];

    protected function casts(): array
    {
        return [
            'main_article_id' => 'integer',
            'task_id' => 'integer',
            'generation_meta' => 'array',
        ];
    }

    public function mainArticle(): BelongsTo
    {
        return $this->belongsTo(Article::class, 'main_article_id')->withTrashed();
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ContentVariant::class)->orderBy('id');
    }
}
