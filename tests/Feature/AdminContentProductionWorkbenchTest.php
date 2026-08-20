<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Author;
use App\Models\Category;
use App\Models\ContentProduction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminContentProductionWorkbenchTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function every_content_production_route_has_consistent_release_guards(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(static fn ($route): bool => str_starts_with(
                (string) $route->getName(),
                'admin.content-productions.',
            ));

        $this->assertNotEmpty($routes);

        foreach ($routes as $route) {
            $middleware = $route->gatherMiddleware();
            $this->assertContains('admin.super', $middleware, $route->getName());
            $this->assertContains('content.production.enabled', $middleware, $route->getName());
        }
    }

    #[Test]
    public function disabled_pipeline_is_hidden_from_super_admins(): void
    {
        config()->set('geoflow.content_production_pipeline_enabled', false);

        $this->actingAs($this->admin('super_admin'), 'admin')
            ->get(route('admin.content-productions.index'))
            ->assertNotFound();
    }

    #[Test]
    public function standard_admin_cannot_open_the_workbench(): void
    {
        config()->set('geoflow.content_production_pipeline_enabled', true);

        $this->actingAs($this->admin('admin'), 'admin')
            ->get(route('admin.content-productions.index'))
            ->assertForbidden();
    }

    #[Test]
    public function super_admin_can_open_and_resume_an_article_work_order_without_rendering_untrusted_html(): void
    {
        config()->set('geoflow.content_production_pipeline_enabled', true);
        $admin = $this->admin('super_admin');
        $production = ContentProduction::factory()->create([
            'created_by_admin_id' => $admin->id,
            'name' => '<script>alert("name")</script>',
            'topic' => '<img src=x onerror=alert("topic")>',
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.content-productions.index'))
            ->assertOk()
            ->assertSee('新建文章')
            ->assertSee('文章工作单')
            ->assertSee('发布包')
            ->assertSee('生产计划')
            ->assertSee('工作队列')
            ->assertSee('打开工作台')
            ->assertSeeText('<script>alert("name")</script>')
            ->assertDontSee('<script>alert("name")</script>', false);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.content-productions.show', $production))
            ->assertOk()
            ->assertSee('文章工作台')
            ->assertSee('文章设置')
            ->assertSee('研究与证据中心')
            ->assertSeeText('<img src=x onerror=alert("topic")>')
            ->assertDontSee('<img src=x onerror=alert("topic")>', false);
    }

    #[Test]
    public function workbench_only_renders_the_selected_safe_stage(): void
    {
        config()->set('geoflow.content_production_pipeline_enabled', true);
        $admin = $this->admin('super_admin');
        $production = ContentProduction::factory()->create(['created_by_admin_id' => $admin->id]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.content-productions.show', ['contentProduction' => $production, 'stage' => 'drafting']))
            ->assertOk()
            ->assertSee('分段写作与文章组装')
            ->assertDontSee('研究与证据中心');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.content-productions.show', ['contentProduction' => $production, 'stage' => 'not-a-stage']))
            ->assertOk()
            ->assertSee('研究与证据中心');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.content-productions.show', $production).'?stage[]=drafting')
            ->assertOk()
            ->assertSee('研究与证据中心')
            ->assertDontSee('分段写作与文章组装');
    }

    #[Test]
    public function super_admin_can_add_article_ownership_to_an_existing_project(): void
    {
        config()->set('geoflow.content_production_pipeline_enabled', true);
        $admin = $this->admin('super_admin');
        $category = Category::query()->create(['name' => '内容生产', 'slug' => 'content-production']);
        $author = Author::query()->create(['name' => '内容团队']);
        $production = ContentProduction::factory()->create([
            'created_by_admin_id' => $admin->id,
            'context' => ['topic' => '原有主题'],
        ]);

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.content-productions.ownership.update', $production), [
                'category_id' => $category->id,
                'author_id' => $author->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('message', '文章分类和作者已保存。');

        $production->refresh();
        $this->assertSame($category->id, data_get($production->context, 'category_id'));
        $this->assertSame($author->id, data_get($production->context, 'author_id'));
        $this->assertSame('原有主题', data_get($production->context, 'topic'));
    }

    private function admin(string $role): Admin
    {
        return Admin::query()->create([
            'username' => 'content_'.$role,
            'password' => 'secret-123',
            'email' => 'content-'.$role.'@example.com',
            'display_name' => 'Content '.$role,
            'role' => $role,
            'status' => 'active',
        ]);
    }
}
