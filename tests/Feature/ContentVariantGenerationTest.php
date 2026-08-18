<?php

namespace Tests\Feature;

use App\Contracts\GeoFlow\ContentVariantGenerator;
use App\Models\Admin;
use App\Models\Article;
use App\Models\Author;
use App\Models\Category;
use App\Models\ContentVariant;
use App\Models\ContentVariantVersion;
use App\Services\GeoFlow\ContentGroupService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ContentVariantGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config()->set('geoflow.content_production_pipeline_enabled', true);
        $this->app->instance(ContentVariantGenerator::class, new FakeContentVariantGenerator);
    }

    public function test_super_admin_can_generate_one_platform_without_changing_main_article_or_other_variants(): void
    {
        $admin = $this->admin('super_admin');
        $article = $this->article();
        $group = app(ContentGroupService::class)->ensureForArticle($article);
        $original = $article->only(['title', 'excerpt', 'content']);
        $other = $group->variants()->where('platform', 'zhihu')->firstOrFail();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.content-groups.variants.generate', $group), ['platforms' => ['baijiahao']])
            ->assertRedirect(route('admin.content-groups.show', $group));

        $variant = $group->variants()->where('platform', 'baijiahao')->firstOrFail();
        $this->assertSame(ContentVariant::STATUS_REVIEW_PENDING, $variant->status);
        $this->assertSame(1, $variant->version);
        $this->assertSame(['平台标签'], $variant->tags);
        $this->assertSame($original, $article->fresh()->only(array_keys($original)));
        $this->assertSame(ContentVariant::STATUS_PENDING, $other->fresh()->status);
        $this->assertDatabaseCount('content_variant_versions', 1);
    }

    public function test_regeneration_creates_immutable_history(): void
    {
        $admin = $this->admin('super_admin');
        $group = app(ContentGroupService::class)->ensureForArticle($this->article());
        $variant = $group->variants()->where('platform', 'toutiao')->firstOrFail();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.content-groups.variants.regenerate', [$group, $variant]))
            ->assertRedirect();
        $first = ContentVariantVersion::query()->firstOrFail();

        $this->post(route('admin.content-groups.variants.regenerate', [$group, $variant]))
            ->assertRedirect();

        $this->assertDatabaseCount('content_variant_versions', 2);
        $this->assertSame(1, $first->fresh()->version);
        $this->assertSame(2, $variant->fresh()->version);
    }

    public function test_generation_requires_super_admin_feature_flag_and_nested_variant(): void
    {
        $admin = $this->admin('admin');
        $first = app(ContentGroupService::class)->ensureForArticle($this->article('第一篇'));
        $second = app(ContentGroupService::class)->ensureForArticle($this->article('第二篇'));
        $foreignVariant = $second->variants()->where('platform', 'sohu')->firstOrFail();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.content-groups.variants.generate', $first), ['platforms' => ['sohu']])
            ->assertForbidden();

        $super = $this->admin('super_admin', 'variant_super');
        $this->actingAs($super, 'admin')
            ->post(route('admin.content-groups.variants.regenerate', [$first, $foreignVariant]))
            ->assertNotFound();

        config()->set('geoflow.content_production_pipeline_enabled', false);
        $this->post(route('admin.content-groups.variants.generate', $first), ['platforms' => ['sohu']])
            ->assertNotFound();
    }

    public function test_wordpress_and_duplicate_generation_are_rejected_without_overwriting_success(): void
    {
        $admin = $this->admin('super_admin');
        $group = app(ContentGroupService::class)->ensureForArticle($this->article());
        $wordpress = $group->variants()->where('platform', 'wordpress')->firstOrFail();
        $busy = $group->variants()->where('platform', 'netease')->firstOrFail();
        $busy->update([
            'status' => ContentVariant::STATUS_GENERATING,
            'generation_token' => '5be15ec7-e495-4acb-91ae-8a99ec25be57',
            'generation_started_at' => now(),
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.content-groups.variants.regenerate', [$group, $wordpress]))
            ->assertSessionHasErrors();

        $this->post(route('admin.content-groups.variants.regenerate', [$group, $busy]))
            ->assertSessionHasErrors();
        $this->assertSame('5be15ec7-e495-4acb-91ae-8a99ec25be57', $busy->fresh()->generation_token);
    }

    public function test_generator_failure_marks_only_claimed_variant_failed(): void
    {
        $this->app->instance(ContentVariantGenerator::class, new FailingContentVariantGenerator);
        $admin = $this->admin('super_admin');
        $group = app(ContentGroupService::class)->ensureForArticle($this->article());
        $variant = $group->variants()->where('platform', 'qq_news')->firstOrFail();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.content-groups.variants.regenerate', [$group, $variant]))
            ->assertSessionHasErrors();

        $this->assertSame(ContentVariant::STATUS_FAILED, $variant->fresh()->status);
        $this->assertSame('生成失败', $variant->fresh()->failure_message);
        $this->assertDatabaseCount('content_variant_versions', 0);
    }

    public function test_generated_content_is_escaped_on_detail_page(): void
    {
        $admin = $this->admin('super_admin');
        $group = app(ContentGroupService::class)->ensureForArticle($this->article());
        $variant = $group->variants()->where('platform', 'baijiahao')->firstOrFail();
        $variant->update(['title' => '<script>alert(1)</script>', 'content' => '<img src=x onerror=alert(1)>']);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.content-groups.show', $group))
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    private function admin(string $role, string $username = 'variant_admin'): Admin
    {
        return Admin::query()->create([
            'username' => $username,
            'password' => 'secret-123',
            'email' => $username.'@example.com',
            'display_name' => 'Variant Admin',
            'role' => $role,
            'status' => 'active',
        ]);
    }

    private function article(string $title = '2026 年多平台内容生产指南'): Article
    {
        $category = Category::query()->create(['name' => $title, 'slug' => uniqid('variant-category-')]);
        $author = Author::query()->create(['name' => 'GEOFlow']);

        return Article::query()->create([
            'title' => $title,
            'slug' => uniqid('variant-article-'),
            'excerpt' => '源文章摘要',
            'content' => '这是一篇包含 2026 年信息和 30% 指标的主文章。',
            'category_id' => $category->id,
            'author_id' => $author->id,
            'status' => 'draft',
            'review_status' => 'approved',
        ]);
    }
}

class FakeContentVariantGenerator implements ContentVariantGenerator
{
    private int $count = 0;

    public function generate(Article $article, string $platform, array $platformRules): array
    {
        $this->count++;

        return [
            'title' => "{$platform} 平台标题 {$this->count}",
            'excerpt' => '平台摘要',
            'content' => '根据源文章改写，保留 2026 年与 30% 两项事实。',
            'tags' => ['平台标签'],
            'image_requirements' => ['横版封面图'],
            'model' => 'fake-model',
            'source' => 'test',
        ];
    }
}

class FailingContentVariantGenerator implements ContentVariantGenerator
{
    public function generate(Article $article, string $platform, array $platformRules): array
    {
        throw new RuntimeException('生成失败');
    }
}
