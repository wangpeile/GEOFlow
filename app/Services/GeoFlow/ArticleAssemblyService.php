<?php

namespace App\Services\GeoFlow;

use App\Enums\ArticleVersionKind;
use App\Enums\ContentDirectionKind;
use App\Enums\ContentProductionStage;
use App\Enums\ContentProductionStatus;
use App\Enums\ContentSectionStatus;
use App\Enums\ContentStageStatus;
use App\Models\Admin;
use App\Models\Article;
use App\Models\ArticleVersion;
use App\Models\Author;
use App\Models\Category;
use App\Models\ContentDirectionVersion;
use App\Models\ContentProduction;
use App\Models\ContentProductionEvent;
use App\Models\ContentSectionVersion;
use App\Support\GeoFlow\ArticleWorkflow;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ArticleAssemblyService
{
    public function __construct(private readonly ChineseContentGuard $chineseContentGuard) {}

    public function assemble(Admin $admin, ContentProduction $production): ArticleVersion
    {
        [$titleVersion, $outlineVersion, $sections] = $this->sources($production);
        $title = trim((string) data_get($titleVersion->payload, 'selected_title'));
        $sectionBody = $this->body($sections);
        $summary = $this->summary($sectionBody);
        $faq = $this->faq($sections);
        $body = $this->appendFaq($sectionBody, $faq);
        $metaTitle = Str::limit($title, 60, '');
        $metaDescription = Str::limit($summary, 160, '');

        $this->chineseContentGuard->validate([
            'title' => $title,
            'body' => $body,
            'summary' => $summary,
            'faq' => collect($faq)->map(
                fn (array $item): string => $item['question'].' '.$item['answer']
            )->implode(' '),
        ]);
        $this->validateLength($production, $body);

        $inputHash = hash('sha256', json_encode([
            'title_version_id' => $titleVersion->id,
            'outline_version_id' => $outlineVersion->id,
            'section_version_ids' => $sections->pluck('id')->all(),
            'assembly_rule' => 'assembly-v1',
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return DB::transaction(function () use (
            $admin,
            $production,
            $title,
            $body,
            $summary,
            $faq,
            $metaTitle,
            $metaDescription,
            $sections,
            $inputHash
        ): ArticleVersion {
            $lockedProduction = ContentProduction::query()
                ->whereKey($production->id)
                ->lockForUpdate()
                ->firstOrFail();
            $existing = ArticleVersion::query()
                ->where('content_production_id', $production->id)
                ->where('input_hash', $inputHash)
                ->first();
            if ($existing) {
                return $existing;
            }

            // A work order first owns an assembled draft and its quality history.  Existing
            // historical work orders may already be linked to a main article, so keep syncing
            // those records for backwards compatibility.  New work orders are promoted only
            // after the quality gate passes.
            $article = null;
            if ($lockedProduction->article_id) {
                [$categoryId, $authorId] = $this->resolveArticleOwnership($lockedProduction);
                $article = Article::query()->lockForUpdate()->findOrFail($lockedProduction->article_id);
                $article->fill([
                    'title' => $title,
                    'excerpt' => $summary,
                    'content' => $body,
                    'category_id' => $categoryId,
                    'author_id' => $authorId,
                    'task_id' => $lockedProduction->task_id,
                    'original_keyword' => $lockedProduction->topic,
                    'keywords' => $lockedProduction->topic,
                    'meta_description' => $metaDescription,
                    'status' => 'draft',
                    'review_status' => 'pending',
                    'is_ai_generated' => 1,
                    'published_at' => null,
                ])->save();
            }

            $version = ArticleVersion::query()->create([
                'content_production_id' => $lockedProduction->id,
                'article_id' => $article?->id,
                'version' => ((int) ArticleVersion::query()
                    ->where('content_production_id', $lockedProduction->id)
                    ->max('version')) + 1,
                'kind' => ArticleVersionKind::Assembled,
                'title' => $title,
                'summary' => $summary,
                'body' => $body,
                'faq' => $faq,
                'meta_title' => $metaTitle,
                'meta_description' => $metaDescription,
                'section_version_ids' => $sections->pluck('id')->all(),
                'input_hash' => $inputHash,
                'created_by_admin_id' => $admin->id,
                'synchronized_at' => now(),
            ]);

            $lockedProduction->forceFill([
                'status' => ContentProductionStatus::WaitingInput,
                'current_stage' => ContentProductionStage::QualityGate,
                'last_error_message' => null,
            ])->save();
            $this->markAssemblySucceeded($lockedProduction, $article, $version);
            ContentProductionEvent::query()->create([
                'content_production_id' => $lockedProduction->id,
                'admin_id' => $admin->id,
                'event' => 'article.assembled',
                'metadata' => [
                    'article_id' => $article?->id,
                    'article_version_id' => $version->id,
                    'section_version_ids' => $sections->pluck('id')->all(),
                ],
            ]);

            return $version;
        });
    }

    /**
     * @return array{ContentDirectionVersion, ContentDirectionVersion, Collection<int, ContentSectionVersion>}
     */
    private function sources(ContentProduction $production): array
    {
        $titleVersion = ContentDirectionVersion::query()
            ->where('content_production_id', $production->id)
            ->where('kind', ContentDirectionKind::Titles)
            ->whereNotNull('confirmed_at')
            ->whereNull('invalidated_at')
            ->latest('version')
            ->first();
        $outlineVersion = ContentDirectionVersion::query()
            ->where('content_production_id', $production->id)
            ->where('kind', ContentDirectionKind::Outlines)
            ->whereNotNull('confirmed_at')
            ->whereNull('invalidated_at')
            ->latest('version')
            ->first();
        if (! $titleVersion || ! $outlineVersion) {
            throw ValidationException::withMessages(['article' => '请先确认标题和大纲。']);
        }

        $candidate = collect($outlineVersion->payload['candidates'] ?? [])
            ->firstWhere('id', data_get($outlineVersion->payload, 'selected_id'));
        $nodes = collect($candidate['nodes'] ?? []);
        $sections = $nodes->map(function (array $node) use ($production, $outlineVersion): ContentSectionVersion {
            $section = ContentSectionVersion::query()
                ->where('content_production_id', $production->id)
                ->where('outline_version_id', $outlineVersion->id)
                ->where('section_key', (string) $node['id'])
                ->where('status', ContentSectionStatus::Succeeded)
                ->latest('version')
                ->first();
            if (! $section) {
                throw ValidationException::withMessages([
                    'sections' => '章节“'.trim((string) $node['heading']).'”尚未成功完成。',
                ]);
            }

            return $section;
        });

        return [$titleVersion, $outlineVersion, $sections];
    }

    /**
     * @param  Collection<int, ContentSectionVersion>  $sections
     */
    private function body(Collection $sections): string
    {
        return $sections->map(function (ContentSectionVersion $section): string {
            $prefix = $section->level === 'h3' ? '###' : '##';

            return $prefix.' '.$section->heading."\n\n".trim((string) $section->content);
        })->implode("\n\n");
    }

    private function summary(string $body): string
    {
        $plain = preg_replace('/^#{2,3}\s+.+$/mu', '', $body) ?: $body;
        $plain = preg_replace('/\s+/u', ' ', strip_tags($plain)) ?: $plain;

        return Str::limit(trim($plain), 180, '');
    }

    /**
     * @param  Collection<int, ContentSectionVersion>  $sections
     * @return list<array{question:string, answer:string}>
     */
    private function faq(Collection $sections): array
    {
        return $sections->take(8)->map(function (ContentSectionVersion $section): array {
            $question = rtrim($section->heading, '？?。').'？';
            $answer = Str::limit(
                trim((string) (preg_replace('/\s+/u', ' ', strip_tags((string) $section->content)) ?: $section->content)),
                180,
                '',
            );

            return ['question' => $question, 'answer' => $answer];
        })->values()->all();
    }

    /**
     * @param  list<array{question:string, answer:string}>  $faq
     */
    private function appendFaq(string $body, array $faq): string
    {
        if ($faq === []) {
            return $body;
        }

        $faqBody = collect($faq)->map(
            fn (array $item): string => '### '.$item['question']."\n\n".$item['answer']
        )->implode("\n\n");

        return $body."\n\n## 常见问题\n\n".$faqBody;
    }

    private function validateLength(ContentProduction $production, string $body): void
    {
        $plainLength = mb_strlen(preg_replace('/\s+/u', '', strip_tags($body)) ?: '');
        $minimum = max(100, (int) data_get($production->context, 'length_min', 500));
        $maximum = max($minimum, (int) data_get($production->context, 'length_max', 5000));
        if ($plainLength < $minimum || $plainLength > $maximum) {
            throw ValidationException::withMessages([
                'body' => "正文长度为 {$plainLength} 字，不符合 {$minimum} 至 {$maximum} 字的篇幅范围。",
            ]);
        }
    }

    /**
     * @return array{int, int}
     */
    private function resolveArticleOwnership(ContentProduction $production): array
    {
        $production->loadMissing('task');
        $categoryId = (int) (data_get($production->context, 'category_id')
            ?: $production->task?->fixed_category_id);
        $authorId = (int) (data_get($production->context, 'author_id')
            ?: $production->task?->custom_author_id
            ?: $production->task?->author_id);
        if ($categoryId < 1 || ! Category::query()->whereKey($categoryId)->exists()) {
            throw ValidationException::withMessages(['category_id' => '请为生产项目指定有效的文章分类。']);
        }
        if ($authorId < 1 || ! Author::query()->whereKey($authorId)->exists()) {
            throw ValidationException::withMessages(['author_id' => '请为生产项目指定有效的文章作者。']);
        }

        return [$categoryId, $authorId];
    }

    private function markAssemblySucceeded(
        ContentProduction $production,
        ?Article $article,
        ArticleVersion $version,
    ): void {
        $run = $production->stageRuns()
            ->where('stage', ContentProductionStage::Assembly->value)
            ->latest('attempt')
            ->first();
        $run?->forceFill([
            'status' => ContentStageStatus::Succeeded,
            'output_payload' => [
                'article_id' => $article?->id,
                'article_version_id' => $version->id,
            ],
            'failure_type' => null,
            'error_message' => null,
            'finished_at' => now(),
            'invalidated_at' => null,
            'invalidation_reason' => null,
        ])->save();
    }
}
