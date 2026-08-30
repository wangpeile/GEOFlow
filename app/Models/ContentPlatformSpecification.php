<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentPlatformSpecification extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_REVIEW_NEEDED = 'review_needed';

    protected $fillable = [
        'platform', 'label', 'version', 'status', 'source_url', 'source_summary',
        'rules', 'verified_at', 'next_review_at',
    ];

    protected function casts(): array
    {
        return [
            'rules' => 'array',
            'verified_at' => 'datetime',
            'next_review_at' => 'datetime',
        ];
    }
}
