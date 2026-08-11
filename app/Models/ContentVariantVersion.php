<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentVariantVersion extends Model
{
    protected $fillable = [
        'content_variant_id',
        'version',
        'title',
        'excerpt',
        'content',
        'tags',
        'image_requirements',
        'template_version',
        'generation_meta',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'content_variant_id' => 'integer',
            'version' => 'integer',
            'tags' => 'array',
            'image_requirements' => 'array',
            'generation_meta' => 'array',
            'created_by' => 'integer',
        ];
    }

    public function contentVariant(): BelongsTo
    {
        return $this->belongsTo(ContentVariant::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }
}
