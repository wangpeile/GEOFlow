<?php

namespace Tests\Feature;

use App\Enums\ArticleVersionKind;
use App\Enums\ContentEvidenceSourceType;
use App\Enums\ContentEvidenceUsage;
use App\Enums\QualityReportStatus;
use App\Exceptions\ContentQualityGateException;
use App\Models\Admin;
use App\Models\Article;
use App\Models\ArticleVersion;
use App\Models\Author;
use App\Models\Category;
use App\Models\ContentEvidence;
use App\Models\ContentProduction;
use App\Models\QualityRepairAttempt;
use App\Models\QualityReport;
use App\Models\SensitiveWord;
use App\Services\GeoFlow\ArticleRiskScanner;
use App\Services\GeoFlow\ArticleWorkflowTransitionService;
use App\Services\GeoFlow\ContentProductionOrchestrator;
use App\Services\GeoFlow\QualityGateService;
use App\Services\GeoFlow\TargetedRepairService;
use App\Support\GeoFlow\ArticleWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ContentQualityGateServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_quality_report_records_blockers_warnings_and_is_idempotent(): void
    {
        [$admin, $production, $version] = $this->context();
        ContentEvidence::query()->create([
            'content_production_id' => $production->id,
            'created_by_admin_id' => $admin->id,
            'source_type' => ContentEvidenceSourceType::Manual,
            'usage' => ContentEvidenceUsage::MustCite,
            'source_key' => 'manual:required-source',
            'source_url' => 'https://example.test/source',
            'source_title' => '企业视频会议调研报告',
            'content_snapshot' => '这是一份经过核验的企业视频会议调研报告。',
            'excerpt' => '经过核验的调研资料。',
            'collected_at' => now(),
        ]);

        $service = app(QualityGateService::class);
        $first = $service->inspect($admin, $production, $version);
        $second = $service->inspect($admin, $production, $version);

        $this->assertTrue($first->is($second));
        $this->assertSame(QualityReportStatus::Blocked, $first->status);
        $this->assertSame(1, QualityReport::query()->count());
        $this->assertContains('required_citation_missing', collect($first->issues)->pluck('code')->all());
        $this->assertGreaterThan(0, $first->summary['blockers']);
        $this->assertSame('targeted_repair', $production->fresh()->current_stage->value);
    }

    public function test_registered_evidence_link_is_not_reported_as_a_fabricated_citation(): void
    {
        [$admin, $production, $version] = $this->context(
            bodySuffix: "\n\n资料来源：https://example.test/source",
        );
        ContentEvidence::query()->create([
            'content_production_id' => $production->id,
            'created_by_admin_id' => $admin->id,
            'source_type' => ContentEvidenceSourceType::Manual,
            'usage' => ContentEvidenceUsage::MustCite,
            'source_key' => 'manual:allowed-source',
            'source_url' => 'https://example.test/source',
            'source_title' => '企业视频会议调研报告',
            'content_snapshot' => '这是一份经过核验的企业视频会议调研报告。',
            'excerpt' => '经过核验的调研资料。',
            'collected_at' => now(),
        ]);

        $report = app(QualityGateService::class)->inspect($admin, $production, $version);

        $this->assertNotContains('required_citation_missing', collect($report->issues)->pluck('code')->all());
        $this->assertNotContains('unverified_citation', collect($report->issues)->pluck('code')->all());
        $this->assertSame(0, $report->summary['blockers']);
    }

    public function test_targeted_repair_creates_a_new_version_and_rechecks_quality(): void
    {
        [$admin, $production, $version] = $this->context(bodySuffix: "\n\n本方案承诺绝对保证实施成功。");
        SensitiveWord::query()->create([
            'word' => '绝对保证',
            'severity' => 'blocked',
            'category' => '夸大承诺',
            'is_enabled' => true,
            'suggestion' => '力求',
            'applies_to' => ['content'],
        ]);
        app(ArticleRiskScanner::class)->clearRuleCache();
        $report = app(QualityGateService::class)->inspect($admin, $production, $version);
        $issue = collect($report->issues)->firstWhere('code', 'sensitive_word');

        $result = app(TargetedRepairService::class)->repair(
            $admin,
            $production,
            $report,
            [$issue['id']],
        );

        $this->assertSame(2, $result['article_version']->version);
        $this->assertSame(ArticleVersionKind::Repair, $result['article_version']->kind);
        $this->assertStringNotContainsString('绝对保证', $result['article_version']->body);
        $this->assertStringContainsString('力求', $result['article_version']->body);
        $this->assertSame(1, QualityRepairAttempt::query()->count());
        $this->assertSame('succeeded', $result['attempt']->status);
        $this->assertNotSame($report->id, $result['quality_report']->id);
        $this->assertNotContains(
            'sensitive_word',
            collect($result['quality_report']->issues)->pluck('code')->all(),
        );
        $this->assertSame($result['article_version']->body, Article::query()->findOrFail($version->article_id)->content);
    }

    public function test_non_repairable_issue_cannot_be_sent_to_automatic_repair(): void
    {
        [$admin, $production, $version] = $this->context(
            bodySuffix: "\n\n未登记链接：https://untrusted.test/reference",
        );
        $report = app(QualityGateService::class)->inspect($admin, $production, $version);
        $issue = collect($report->issues)->firstWhere('code', 'unverified_citation');

        $this->expectException(ValidationException::class);
        app(TargetedRepairService::class)->repair($admin, $production, $report, [$issue['id']]);
    }

    public function test_blocked_quality_report_prevents_publication(): void
    {
        [$admin, $production, $version] = $this->context(
            bodySuffix: "\n\n未登记链接：https://untrusted.test/reference",
        );
        app(QualityGateService::class)->inspect($admin, $production, $version);
        $article = $version->article()->firstOrFail();
        $article->update(['review_status' => 'approved']);

        $this->expectException(ContentQualityGateException::class);
        app(ArticleWorkflowTransitionService::class)->transition(
            $article,
            ArticleWorkflow::normalizeState('published', 'approved'),
            'quality-test',
        );
    }

    public function test_warning_quality_report_can_enter_review_and_publish_after_approval(): void
    {
        [$admin, $production, $version] = $this->context();
        $version->update(['faq' => array_slice($version->faq, 0, 2)]);
        $report = app(QualityGateService::class)->inspect($admin, $production, $version->fresh());

        $this->assertSame(QualityReportStatus::Warning, $report->status);
        $this->assertSame('review', $production->fresh()->current_stage->value);

        $article = $version->article()->firstOrFail();
        $article->update(['review_status' => 'approved']);
        $published = app(ArticleWorkflowTransitionService::class)->transition(
            $article,
            ArticleWorkflow::normalizeState('published', 'approved'),
            'quality-test',
        );

        $this->assertSame('published', $published->status);
    }

    public function test_sensitive_word_dictionary_change_invalidates_cached_quality_result(): void
    {
        [$admin, $production, $version] = $this->context();
        $first = app(QualityGateService::class)->inspect($admin, $production, $version);
        SensitiveWord::query()->create([
            'word' => '真实业务目标',
            'severity' => 'blocked',
            'category' => '测试规则',
            'is_enabled' => true,
            'applies_to' => ['content'],
        ]);
        app(ArticleRiskScanner::class)->clearRuleCache();

        $second = app(QualityGateService::class)->inspect($admin, $production, $version);

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(QualityReportStatus::Blocked, $second->status);
    }

    /**
     * @return array{Admin, ContentProduction, ArticleVersion}
     */
    private function context(string $bodySuffix = ''): array
    {
        $admin = Admin::query()->create([
            'username' => 'quality-admin',
            'password' => 'secret',
            'role' => 'super_admin',
            'status' => 'active',
        ]);
        $production = app(ContentProductionOrchestrator::class)->create($admin, [
            'name' => '质量门禁测试',
            'topic' => '企业视频会议系统',
            'mode' => 'guided',
            'language' => 'zh_CN',
            'target_platforms' => ['wordpress'],
        ]);
        $production->forceFill([
            'context' => [
                'topic' => '企业视频会议系统',
                'language' => 'zh_CN',
                'length_min' => 300,
                'length_max' => 5000,
            ],
        ])->save();
        $category = Category::query()->create([
            'name' => '质量门禁',
            'slug' => 'quality-gate',
        ]);
        $author = Author::query()->create([
            'name' => '质量团队',
        ]);
        $paragraph = '企业视频会议系统需要围绕真实业务目标进行规划。团队应核对网络、安全、终端、运维和培训条件，并记录每项决策依据。';
        $body = "## 企业视频会议系统是什么\n\n".str_repeat($paragraph, 3)
            ."\n\n## 如何建立实施标准\n\n".str_repeat('实施团队应明确负责人、验收指标和故障处理机制，确保每个步骤都有记录并可以复核。', 4)
            ."\n\n## 常见问题\n\n### 如何开始？\n\n先明确业务范围和目标。\n\n### 如何验收？\n\n按照真实指标验收。\n\n### 如何改进？\n\n根据使用反馈持续调整。"
            .$bodySuffix;
        $article = Article::query()->create([
            'title' => '企业视频会议系统选型与实施指南',
            'slug' => 'quality-gate-test',
            'category_id' => $category->id,
            'author_id' => $author->id,
            'excerpt' => '本文介绍企业视频会议系统的选型、实施与验收方法。',
            'content' => $body,
            'original_keyword' => $production->topic,
            'keywords' => $production->topic,
            'meta_description' => '企业视频会议系统选型与实施指南，涵盖目标、步骤和验收方法。',
            'status' => 'draft',
            'review_status' => 'pending',
        ]);
        $version = ArticleVersion::query()->create([
            'content_production_id' => $production->id,
            'article_id' => $article->id,
            'version' => 1,
            'kind' => ArticleVersionKind::Assembled,
            'title' => $article->title,
            'summary' => $article->excerpt,
            'body' => $body,
            'faq' => [
                ['question' => '如何开始？', 'answer' => '先明确业务范围和目标。'],
                ['question' => '如何验收？', 'answer' => '按照真实指标验收。'],
                ['question' => '如何改进？', 'answer' => '根据使用反馈持续调整。'],
            ],
            'meta_title' => $article->title,
            'meta_description' => $article->meta_description,
            'section_version_ids' => [],
            'input_hash' => hash('sha256', 'quality-version-'.$bodySuffix),
            'created_by_admin_id' => $admin->id,
            'synchronized_at' => now(),
        ]);
        $production->forceFill([
            'article_id' => $article->id,
            'status' => 'waiting_input',
            'current_stage' => 'quality_gate',
        ])->save();

        return [$admin, $production->fresh(), $version];
    }
}
