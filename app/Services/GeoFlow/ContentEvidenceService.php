<?php

namespace App\Services\GeoFlow;

use App\Enums\ContentEvidenceSourceType;
use App\Enums\ContentEvidenceUsage;
use App\Models\Admin;
use App\Models\ContentEvidence;
use App\Models\ContentProduction;
use App\Models\KnowledgeBase;
use App\Models\UrlImportJob;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ContentEvidenceService
{
    public function __construct(private readonly KnowledgeRetrievalService $retrievalService) {}

    /**
     * @return Collection<int, ContentEvidence>
     */
    public function retrieveKnowledge(
        Admin $admin,
        ContentProduction $production,
        KnowledgeBase $knowledgeBase,
        string $query,
        int $limit = 5,
    ): Collection {
        $candidates = $this->retrievalService->retrieveEvidence($knowledgeBase->getKey(), $query, $limit);

        if ($candidates === []) {
            throw ValidationException::withMessages([
                'knowledge_base_id' => '未检索到可用证据。请补充知识库资料或手工添加证据后再继续。',
            ]);
        }

        return DB::transaction(function () use ($admin, $production, $knowledgeBase, $candidates, $query): Collection {
            ContentProduction::query()->whereKey($production->getKey())->lockForUpdate()->firstOrFail();
            $evidences = collect();

            foreach ($candidates as $candidate) {
                $metadata = is_array($candidate['metadata'] ?? null) ? $candidate['metadata'] : [];
                $chunkId = (int) ($candidate['knowledge_chunk_id'] ?? 0);
                $content = trim((string) ($candidate['content'] ?? ''));

                if ($chunkId < 1 || $content === '') {
                    continue;
                }

                $evidence = ContentEvidence::query()->firstOrNew([
                    'content_production_id' => $production->getKey(),
                    'source_key' => 'knowledge_chunk:'.$chunkId,
                ]);
                $evidence->fill([
                    'knowledge_base_id' => $knowledgeBase->getKey(),
                    'knowledge_chunk_id' => $chunkId,
                    'created_by_admin_id' => $admin->getKey(),
                    'source_type' => ContentEvidenceSourceType::KnowledgeChunk,
                    'source_url' => $metadata['source_url'] ?? $knowledgeBase->source_url,
                    'source_title' => trim((string) ($candidate['chunk_title'] ?? '')) ?: $knowledgeBase->name ?: '未命名知识库来源',
                    'content_snapshot' => $content,
                    'excerpt' => Str::limit($content, 500, ''),
                    'confidence' => max(0, min(1, (float) ($candidate['score'] ?? 0))),
                    'metadata' => [
                        'query' => $query,
                        'chunk_index' => (int) ($candidate['chunk_index'] ?? 0),
                        'section_path' => $candidate['section_path'] ?? null,
                        'retrieval_scores' => [
                            'vector' => $candidate['vector_score'] ?? null,
                            'keyword' => $candidate['keyword_score'] ?? null,
                            'title' => $candidate['title_score'] ?? null,
                        ],
                    ],
                    'collected_at' => now(),
                ]);
                if (! $evidence->exists) {
                    $evidence->usage = ContentEvidenceUsage::ReferenceOnly;
                }
                $evidence->save();
                $evidences->push($evidence);
            }

            if ($evidences->isEmpty()) {
                throw ValidationException::withMessages([
                    'knowledge_base_id' => '检索结果没有可保存的正文，请补充资料后再继续。',
                ]);
            }

            $this->audit($production, $admin, 'knowledge_evidence_retrieved', [
                'knowledge_base_id' => $knowledgeBase->getKey(),
                'query' => $query,
                'count' => $evidences->count(),
            ]);

            return $evidences;
        });
    }

    public function attachUrlImport(Admin $admin, ContentProduction $production, UrlImportJob $job): ContentEvidence
    {
        if ($job->status !== 'completed') {
            throw ValidationException::withMessages(['url_import_job_id' => '只能添加已完成的 URL 采集任务。']);
        }

        $result = json_decode((string) $job->result_json, true);
        $result = is_array($result) ? $result : [];
        $page = is_array($result['page'] ?? null) ? $result['page'] : [];
        $source = is_array($result['source'] ?? null) ? $result['source'] : [];
        $content = trim((string) ($page['text'] ?? ''));

        if ($content === '') {
            throw ValidationException::withMessages(['url_import_job_id' => '该 URL 采集任务没有可用正文。']);
        }

        return DB::transaction(function () use ($admin, $production, $job, $page, $source, $content): ContentEvidence {
            ContentProduction::query()->whereKey($production->getKey())->lockForUpdate()->firstOrFail();
            $evidence = ContentEvidence::query()->firstOrNew([
                'content_production_id' => $production->getKey(),
                'source_key' => 'url_import:'.$job->getKey(),
            ]);
            $evidence->fill([
                'url_import_job_id' => $job->getKey(),
                'created_by_admin_id' => $admin->getKey(),
                'source_type' => ContentEvidenceSourceType::UrlImport,
                'source_url' => $job->normalized_url ?: $job->url,
                'source_title' => trim((string) ($page['title'] ?? $job->page_title)) ?: $job->source_domain ?: '未命名 URL 来源',
                'content_snapshot' => $content,
                'excerpt' => Str::limit(trim((string) ($page['summary'] ?? $content)), 500, ''),
                'metadata' => [
                    'domain' => $job->source_domain,
                    'description' => $page['description'] ?? null,
                    'http_status' => $source['status'] ?? null,
                ],
                'collected_at' => $source['fetched_at'] ?? $job->finished_at ?? now(),
            ]);
            if (! $evidence->exists) {
                $evidence->usage = ContentEvidenceUsage::ReferenceOnly;
            }
            $evidence->save();

            $this->audit($production, $admin, 'url_evidence_attached', [
                'content_evidence_id' => $evidence->getKey(),
                'url_import_job_id' => $job->getKey(),
            ]);

            return $evidence;
        });
    }

    /**
     * @param  array{source_title: string, source_url?: string|null, content_snapshot: string, usage: string}  $attributes
     */
    public function addManual(Admin $admin, ContentProduction $production, array $attributes): ContentEvidence
    {
        return DB::transaction(function () use ($admin, $production, $attributes): ContentEvidence {
            $evidence = $production->evidences()->create([
                'created_by_admin_id' => $admin->getKey(),
                'source_type' => ContentEvidenceSourceType::Manual,
                'usage' => ContentEvidenceUsage::from($attributes['usage']),
                'source_key' => 'manual:'.Str::uuid(),
                'source_url' => $attributes['source_url'] ?? null,
                'source_title' => $attributes['source_title'],
                'content_snapshot' => $attributes['content_snapshot'],
                'excerpt' => Str::limit($attributes['content_snapshot'], 500, ''),
                'collected_at' => now(),
            ]);

            $this->audit($production, $admin, 'manual_evidence_added', ['content_evidence_id' => $evidence->getKey()]);

            return $evidence;
        });
    }

    public function updateUsage(Admin $admin, ContentProduction $production, ContentEvidence $evidence, ContentEvidenceUsage $usage): void
    {
        DB::transaction(function () use ($admin, $production, $evidence, $usage): void {
            $previous = $evidence->usage;
            $evidence->update(['usage' => $usage]);
            $this->audit($production, $admin, 'evidence_usage_updated', [
                'content_evidence_id' => $evidence->getKey(),
                'from' => $previous->value,
                'to' => $usage->value,
            ]);
        });
    }

    public function delete(Admin $admin, ContentProduction $production, ContentEvidence $evidence): void
    {
        DB::transaction(function () use ($admin, $production, $evidence): void {
            $metadata = [
                'content_evidence_id' => $evidence->getKey(),
                'source_type' => $evidence->source_type->value,
                'source_title' => $evidence->source_title,
            ];
            $evidence->delete();
            $this->audit($production, $admin, 'evidence_deleted', $metadata);
        });
    }

    private function audit(ContentProduction $production, Admin $admin, string $event, array $metadata): void
    {
        $production->events()->create([
            'admin_id' => $admin->getKey(),
            'event' => $event,
            'metadata' => $metadata,
        ]);
    }
}
