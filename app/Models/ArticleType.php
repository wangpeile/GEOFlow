<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ArticleType extends Model
{
    protected $fillable = [
        'code', 'name', 'description', 'default_settings', 'is_system', 'is_active', 'created_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'default_settings' => 'array',
            'is_system' => 'boolean',
            'is_active' => 'boolean',
            'created_by_admin_id' => 'integer',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    public function writingRules(): HasMany
    {
        return $this->hasMany(WritingRule::class);
    }
}
