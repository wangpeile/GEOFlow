<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Article;
use App\Models\Author;
use App\Models\Category;
use App\Models\ContentGroup;
use App\Models\ContentVariant;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminContentGroupsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_guest_is_redirected_to_admin_login(): void
    {
        $this->get(route('admin.content-groups.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_create_an_idempotent_eight_platform_content_group(): void
    {
        config()->set('geoflow.content_production_pipeline_enabled', true);
        $admin = $this->admin('content_group_admin', 'super_admin');
        $article = $this->article();
        $original = $article->only(['title', 'excerpt', 'content', 'status', 'review_status']);

        $firstResponse = $this->actingAs($admin, 'admin')
            ->post(route('admin.content-groups.store', $article));

        $contentGroup = ContentGroup::query()->where('main_article_id', $article->id)->firstOrFail();
        $firstResponse->assertRedirect(route('admin.content-groups.show', $contentGroup));
        $this->assertSame(8, $contentGroup->variants()->count());
        $this->assertSame(8, $contentGroup->variants()->distinct()->count('platform'));

        $wordpress = $contentGroup->variants()->where('platform', 'wordpress')->firstOrFail();
        $this->assertSame(ContentVariant::STATUS_READY, $wordpress->status);
        $this->assertSame($original['title'], $wordpress->title);
        $this->assertSame($original['excerpt'], $wordpress->excerpt);
        $this->assertSame($original['content'], $wordpress->content);
        $this->assertSame(7, $contentGroup->variants()->where('status', ContentVariant::STATUS_PENDING)->count());
        $this->assertSame($original, $article->fresh()->only(array_keys($original)));

        $this->post(route('admin.content-groups.store', $article))
            ->assertRedirect(route('admin.content-groups.show', $contentGroup));

        $this->assertSame(1, ContentGroup::query()->where('main_article_id', $article->id)->count());
        $this->assertSame(8, $contentGroup->variants()->count());
    }

    public function test_super_admin_can_view_content_group_index_and_all_platforms(): void
    {
        config()->set('geoflow.content_production_pipeline_enabled', true);
        $admin = $this->admin('content_group_viewer', 'super_admin');
        $article = $this->article('内容组页面测试文章');

        $this->actingAs($admin, 'admin')
            ->post(route('admin.content-groups.store', $article));

        $contentGroup = ContentGroup::query()->firstOrFail();

        $this->get(route('admin.content-groups.index'))
            ->assertOk()
            ->assertSee(__('admin.content_groups.heading'))
            ->assertSee($article->title);

        $response = $this->get(route('admin.content-groups.show', $contentGroup))
            ->assertOk()
            ->assertSee($article->title);

        foreach (config('content_platforms') as $platform) {
            $response->assertSee($platform['label']);
        }
    }

    private function admin(string $username = 'content_group_admin', string $role = 'admin'): Admin
    {
        return Admin::query()->create([
            'username' => $username,
            'password' => 'secret-123',
            'email' => $username.'@example.com',
            'display_name' => 'Content Group Admin',
            'role' => $role,
            'status' => 'active',
        ]);
    }

    private function article(string $title = '用于多平台改写的主文章'): Article
    {
        $category = Category::query()->create([
            'name' => '内容组分类',
            'slug' => 'content-group-category-'.uniqid(),
        ]);
        $author = Author::query()->create(['name' => 'GEOFlow']);

        return Article::query()->create([
            'title' => $title,
            'slug' => 'content-group-article-'.uniqid(),
            'excerpt' => '主文章摘要',
            'content' => "## 主文章\n\n这里是不会被平台改写覆盖的正文。",
            'category_id' => $category->id,
            'author_id' => $author->id,
            'status' => 'draft',
            'review_status' => 'approved',
        ]);
    }
}
