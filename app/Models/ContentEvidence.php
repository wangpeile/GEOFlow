<?php

namespace App\Models;

use App\Enums\ContentEvidenceSourceType;
use App\Enums\ContentEvidenceUsage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentEvidence extends Model
{
    use HasFactory;

    protected $table = 'content_evidences';

    protected $fillable = [
        'content_production_id',
        'knowledge_base_id',
        'knowledge_chunk_id',
        'url_import_job_id',
        'created_by_admin_id',
        'source_type',
        'usage',
        'source_key',
        'source_url',
        'source_title',
        'content_snapshot',
        'excerpt',
        'confidence',
        'metadata',
        'collected_at',
    ];

    protected function casts(): array
    {
        return [
            'content_production_id' => 'integer',
            'knowledge_base_id' => 'integer',
            'knowledge_chunk_id' => 'integer',
            'url_import_job_id' => 'integer',
            'created_by_admin_id' => 'integer',
            'source_type' => ContentEvidenceSourceType::class,
            'usage' => ContentEvidenceUsage::class,
            'confidence' => 'float',
            'metadata' => 'array',
            'collected_at' => 'datetime',
        ];
    }

    public function contentProduction(): BelongsTo
    {
        return $this->belongsTo(ContentProduction::class);
    }

    public function knowledgeBase(): BelongsTo
    {
        return $this->belongsTo(KnowledgeBase::class);
    }

    public function knowledgeChunk(): BelongsTo
    {
        return $this->belongsTo(KnowledgeChunk::class);
    }

    public function urlImportJob(): BelongsTo
    {
        return $this->belongsTo(UrlImportJob::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }
}
