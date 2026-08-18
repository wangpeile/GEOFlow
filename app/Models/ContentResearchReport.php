<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentResearchReport extends Model
{
    protected $fillable = [
        'content_production_id', 'created_by_admin_id', 'keyword', 'status',
        'source_mode', 'sources', 'analysis', 'error_message', 'collected_at',
    ];

    protected function casts(): array
    {
        return [
            'content_production_id' => 'integer',
            'created_by_admin_id' => 'integer',
            'sources' => 'array',
            'analysis' => 'array',
            'collected_at' => 'datetime',
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
}
