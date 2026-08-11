<?php

namespace App\Models;

use App\Enums\ContentSectionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentSectionVersion extends Model
{
    protected $attributes = [
        'status' => 'pending',
        'generation_source' => 'manual',
    ];

    protected $fillable = [
        'content_production_id',
        'outline_version_id',
        'section_key',
        'heading',
        'level',
        'position',
        'version',
        'status',
        'content',
        'evidence_ids',
        'input_hash',
        'model',
        'prompt_version',
        'generation_source',
        'error_message',
        'created_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'content_production_id' => 'integer',
            'outline_version_id' => 'integer',
            'position' => 'integer',
            'version' => 'integer',
            'status' => ContentSectionStatus::class,
            'evidence_ids' => 'array',
            'created_by_admin_id' => 'integer',
        ];
    }

    public function contentProduction(): BelongsTo
    {
        return $this->belongsTo(ContentProduction::class);
    }

    public function outlineVersion(): BelongsTo
    {
        return $this->belongsTo(ContentDirectionVersion::class, 'outline_version_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }
}
