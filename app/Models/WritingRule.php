<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class WritingRule extends Model
{
    protected $fillable = [
        'uuid', 'article_type_id', 'created_by_admin_id', 'name', 'description', 'is_preset', 'is_active', 'current_version',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $rule): void {
            $rule->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'article_type_id' => 'integer',
            'created_by_admin_id' => 'integer',
            'is_preset' => 'boolean',
            'is_active' => 'boolean',
            'current_version' => 'integer',
        ];
    }

    public function articleType(): BelongsTo
    {
        return $this->belongsTo(ArticleType::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(WritingRuleVersion::class)->latest('version');
    }

    public function currentVersionRecord(): ?WritingRuleVersion
    {
        return $this->versions()->where('version', $this->current_version)->first();
    }
}
