<?php

namespace Tests\Feature;

use App\Models\Admin;
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
    public function super_admin_can_open_and_resume_a_project_without_rendering_untrusted_html(): void
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
            ->assertSee('选择创作方式')
            ->assertSee('继续创作')
            ->assertSeeText('<script>alert("name")</script>')
            ->assertDontSee('<script>alert("name")</script>', false);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.content-productions.show', $production))
            ->assertOk()
            ->assertSee('主题与平台')
            ->assertSee('研究与证据中心')
            ->assertSeeText('<img src=x onerror=alert("topic")>')
            ->assertDontSee('<img src=x onerror=alert("topic")>', false);
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
