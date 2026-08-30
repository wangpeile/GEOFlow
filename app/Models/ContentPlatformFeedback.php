<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentPlatformFeedback extends Model
{
    protected $table = 'content_platform_feedback';

    protected $fillable = [
        'content_variant_id', 'admin_id', 'outcome', 'reasons', 'manual_adjustments', 'notes', 'submitted_at',
    ];

    protected function casts(): array
    {
        return ['reasons' => 'array', 'manual_adjustments' => 'array', 'submitted_at' => 'datetime'];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ContentVariant::class, 'content_variant_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }
}
