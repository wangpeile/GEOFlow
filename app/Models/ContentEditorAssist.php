<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentEditorAssist extends Model
{
    protected $fillable = [
        'article_id', 'created_by_admin_id', 'action', 'selection_hash', 'source_text',
        'instruction', 'result_text', 'model', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'article_id' => 'integer',
            'created_by_admin_id' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }
}
