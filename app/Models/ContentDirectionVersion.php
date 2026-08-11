<?php

namespace App\Models;

use App\Enums\ContentDirectionKind;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentDirectionVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'content_production_id',
        'kind',
        'version',
        'payload',
        'input_hash',
        'source_version_ids',
        'created_by_admin_id',
        'confirmed_by_admin_id',
        'confirmed_at',
        'invalidated_at',
        'invalidation_reason',
    ];

    protected function casts(): array
    {
        return [
            'content_production_id' => 'integer',
            'kind' => ContentDirectionKind::class,
            'version' => 'integer',
            'payload' => 'array',
            'source_version_ids' => 'array',
            'created_by_admin_id' => 'integer',
            'confirmed_by_admin_id' => 'integer',
            'confirmed_at' => 'datetime',
            'invalidated_at' => 'datetime',
        ];
    }

    public function contentProduction(): BelongsTo
    {
        return $this->belongsTo(ContentProduction::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'confirmed_by_admin_id');
    }
}
