<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContentTopic extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'website', 'audience', 'category_id', 'writing_rule_id', 'knowledge_base_ids',
        'reference_urls', 'material_scope', 'keyword_clusters', 'content_goal', 'publishing_cadence',
        'is_active', 'created_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'category_id' => 'integer',
            'writing_rule_id' => 'integer',
            'knowledge_base_ids' => 'array',
            'reference_urls' => 'array',
            'keyword_clusters' => 'array',
            'is_active' => 'boolean',
            'created_by_admin_id' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function writingRule(): BelongsTo
    {
        return $this->belongsTo(WritingRule::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    public function ideas(): HasMany
    {
        return $this->hasMany(ContentTopicIdea::class)->latest('id');
    }

    public function productions(): HasMany
    {
        return $this->hasMany(ContentProduction::class);
    }

    public function productionPlans(): HasMany
    {
        return $this->hasMany(Task::class, 'content_topic_id');
    }
}
