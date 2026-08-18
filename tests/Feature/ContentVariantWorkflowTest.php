<?php

namespace Tests\Feature;

use App\Contracts\GeoFlow\ContentVariantGenerator;
use App\Jobs\ProcessWordPressContentPublicationJob;
use App\Models\Admin;
use App\Models\Article;
use App\Models\Author;
use App\Models\Category;
use App\Models\ContentGroup;
use App\Models\ContentVariant;
use App\Models\DistributionChannel;
use App\Services\GeoFlow\ContentGroupService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ContentVariantWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config()->set('geoflow.content_production_pipeline_enabled', true);
        $this->app->instance(ContentVariantGenerator::class, new WorkflowVariantGenerator);
    }

    public function test_manual_edit_creates_an_immutable_version_and_resets_review(): void
    {
        [$admin, $group, $variant] = $this->generatedVariant('baijiahao');
        $firstVersion = $variant->versions()->firstOrFail();
        $other = $group->variants()->where('platform', 'zhihu')->firstOrFail();
        $mainContent = $group->mainArticle->content;
        $editedContent = str_repeat('这是人工编辑后的中文平台内容，包含方法说明、适用场景和注意事项。', 45);

        $this->actingAs($admin, 'admin')->put(route('admin.content-groups.variants.update', [$group, $variant]), [
            'current_version' => 1,
            'title' => '人工编辑后的平台标题',
            'excerpt' => '人工编辑摘要',
            'content' => $editedContent,
            'tags' => "内容生产\n平台运营",
            'image_requirements' => '横版中文封面',
        ])->assertRedirect(route('admin.content-groups.show', $group));

        $variant->refresh();
        $this->assertSame(2, $variant->version);
        $this->assertSame(ContentVariant::REVIEW_PENDING, $variant->review_status);
        $this->assertSame('generated', $firstVersion->fresh()->change_type);
        $this->assertSame('manual_edit', $variant->versions()->where('version', 2)->firstOrFail()->change_type);
        $this->assertSame($mainContent, $group->mainArticle->fresh()->content);
        $this->assertSame(ContentVariant::STATUS_PENDING, $other->fresh()->status);
    }

    public function test_quality_passed_version_can_be_approved_and_exported(): void
    {
        [$admin, $group, $variant] = $this->generatedVariant('sohu');

        $this->actingAs($admin, 'admin')->post(route('admin.content-groups.variants.review', [$group, $variant]), [
            'current_version' => 1,
            'decision' => ContentVariant::REVIEW_APPROVED,
            'note' => '核对通过',
        ])->assertRedirect();

        $variant->refresh();
        $this->assertSame(ContentVariant::STATUS_READY, $variant->status);
        $this->assertSame(ContentVariant::REVIEW_APPROVED, $variant->review_status);
        $this->assertDatabaseHas('content_variant_reviews', [
            'content_variant_id' => $variant->id,
            'decision' => ContentVariant::REVIEW_APPROVED,
            'reviewer_id' => $admin->id,
        ]);

        $this->post(route('admin.content-groups.variants.export', $group), ['variant_ids' => [$variant->id]])
            ->assertOk()
            ->assertHeader('content-type', 'application/zip');
    }

    public function test_reject_requires_a_note_and_stale_review_is_rejected(): void
    {
        [$admin, $group, $variant] = $this->generatedVariant('toutiao');

        $this->actingAs($admin, 'admin')->post(route('admin.content-groups.variants.review', [$group, $variant]), [
            'current_version' => 1,
            'decision' => ContentVariant::REVIEW_REJECTED,
        ])->assertSessionHasErrors('note');

        $this->post(route('admin.content-groups.variants.review', [$group, $variant]), [
            'current_version' => 99,
            'decision' => ContentVariant::REVIEW_APPROVED,
        ])->assertSessionHasErrors();
        $this->assertSame(ContentVariant::REVIEW_PENDING, $variant->fresh()->review_status);
    }

    public function test_unapproved_and_cross_group_variants_cannot_be_exported_or_edited(): void
    {
        [$admin, $group, $variant] = $this->generatedVariant('netease');
        $otherGroup = app(ContentGroupService::class)->ensureForArticle($this->article('另一篇文章'));

        $this->actingAs($admin, 'admin')
            ->get(route('admin.content-groups.variants.edit', [$otherGroup, $variant]))
            ->assertNotFound();
        $this->post(route('admin.content-groups.variants.export', $group), ['variant_ids' => [$variant->id]])
            ->assertSessionHasErrors();
    }

    public function test_wordpress_draft_publication_is_versioned_and_idempotent(): void
    {
        Queue::fake();
        $admin = $this->admin();
        $group = app(ContentGroupService::class)->ensureForArticle($this->article());
        $variant = $group->variants()->where('platform', 'wordpress')->firstOrFail();
        $channel = DistributionChannel::query()->create([
            'name' => '测试 WordPress',
            'domain' => 'wp.example.test',
            'endpoint_url' => 'https://wp.example.test/wp-json',
            'channel_type' => 'wordpress_rest',
            'status' => DistributionChannel::STATUS_ACTIVE,
        ]);
        $payload = [
            'distribution_channel_id' => $channel->id,
            'publication_mode' => 'draft',
        ];

        $route = route('admin.content-groups.variants.wordpress.publish', [$group, $variant]);
        $this->actingAs($admin, 'admin')->post($route, $payload)->assertRedirect();
        $this->post($route, $payload)->assertRedirect();

        $this->assertDatabaseCount('article_distributions', 1);
        $this->assertDatabaseHas('article_distributions', [
            'content_group_id' => $group->id,
            'content_variant_id' => $variant->id,
            'publication_mode' => 'draft',
            'published_version' => 1,
            'status' => 'queued',
        ]);
        $this->assertDatabaseHas('content_variant_versions', [
            'content_variant_id' => $variant->id,
            'version' => 1,
            'change_type' => 'source_snapshot',
        ]);
        Queue::assertPushed(ProcessWordPressContentPublicationJob::class, 2);
    }

    public function test_unapproved_article_cannot_be_formally_published_to_wordpress(): void
    {
        Queue::fake();
        $admin = $this->admin();
        $article = $this->article();
        $article->update(['review_status' => 'pending']);
        $group = app(ContentGroupService::class)->ensureForArticle($article);
        $variant = $group->variants()->where('platform', 'wordpress')->firstOrFail();
        $channel = DistributionChannel::query()->create([
            'name' => '正式发布测试站点',
            'domain' => 'wp-publish.example.test',
            'endpoint_url' => 'https://wp-publish.example.test/wp-json',
            'channel_type' => 'wordpress_rest',
            'status' => DistributionChannel::STATUS_ACTIVE,
        ]);

        $this->actingAs($admin, 'admin')->post(
            route('admin.content-groups.variants.wordpress.publish', [$group, $variant]),
            ['distribution_channel_id' => $channel->id, 'publication_mode' => 'immediate'],
        )->assertSessionHasErrors();

        $this->assertDatabaseCount('article_distributions', 0);
        Queue::assertNothingPushed();
    }

    /** @return array{Admin, ContentGroup, ContentVariant} */
    private function generatedVariant(string $platform): array
    {
        $admin = $this->admin();
        $group = app(ContentGroupService::class)->ensureForArticle($this->article());
        $variant = $group->variants()->where('platform', $platform)->firstOrFail();
        $this->actingAs($admin, 'admin')
            ->post(route('admin.content-groups.variants.regenerate', [$group, $variant]))
            ->assertRedirect();

        return [$admin, $group->fresh('mainArticle'), $variant->fresh()];
    }

    private function admin(): Admin
    {
        return Admin::query()->create([
            'username' => 'workflow-admin-'.uniqid(),
            'password' => 'secret-123',
            'email' => uniqid('workflow-').'@example.com',
            'display_name' => 'Workflow Admin',
            'role' => 'super_admin',
            'status' => 'active',
        ]);
    }

    private function article(string $title = '平台内容工作流测试文章'): Article
    {
        $category = Category::query()->create(['name' => $title, 'slug' => uniqid('workflow-category-')]);
        $author = Author::query()->create(['name' => 'GEOFlow']);

        return Article::query()->create([
            'title' => $title,
            'slug' => uniqid('workflow-article-'),
            'excerpt' => '中文源文章摘要',
            'content' => str_repeat('这是源文章中的中文事实和方法说明。', 70),
            'category_id' => $category->id,
            'author_id' => $author->id,
            'status' => 'draft',
            'review_status' => 'approved',
        ]);
    }
}

class WorkflowVariantGenerator implements ContentVariantGenerator
{
    public function generate(Article $article, string $platform, array $platformRules): array
    {
        return [
            'title' => '适合平台发布的中文标题',
            'excerpt' => '适合平台发布的中文摘要',
            'content' => str_repeat('这是源文章中的中文事实和方法说明。', 70),
            'tags' => ['内容生产', '平台运营'],
            'image_requirements' => ['横版中文封面'],
            'model' => 'workflow-fake',
            'source' => 'test',
        ];
    }
}
