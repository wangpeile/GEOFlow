<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentVariantReview extends Model
{
    protected $fillable = [
        'content_variant_id',
        'content_variant_version_id',
        'reviewer_id',
        'decision',
        'note',
        'quality_check',
    ];

    protected function casts(): array
    {
        return [
            'content_variant_id' => 'integer',
            'content_variant_version_id' => 'integer',
            'reviewer_id' => 'integer',
            'quality_check' => 'array',
        ];
    }

    public function contentVariant(): BelongsTo
    {
        return $this->belongsTo(ContentVariant::class);
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ContentVariantVersion::class, 'content_variant_version_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewer_id');
    }
}
