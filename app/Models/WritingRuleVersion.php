<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WritingRuleVersion extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'writing_rule_id', 'article_type_id', 'created_by_admin_id', 'version', 'settings', 'settings_hash', 'change_note',
    ];

    protected function casts(): array
    {
        return [
            'writing_rule_id' => 'integer',
            'article_type_id' => 'integer',
            'created_by_admin_id' => 'integer',
            'version' => 'integer',
            'settings' => 'array',
        ];
    }

    public function writingRule(): BelongsTo
    {
        return $this->belongsTo(WritingRule::class);
    }

    public function articleType(): BelongsTo
    {
        return $this->belongsTo(ArticleType::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }
}
