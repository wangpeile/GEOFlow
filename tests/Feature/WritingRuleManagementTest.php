<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\ArticleType;
use App\Models\Author;
use App\Models\Category;
use App\Models\ContentProduction;
use App\Models\WritingRule;
use App\Services\GeoFlow\WritingRuleVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WritingRuleManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('geoflow.content_production_pipeline_enabled', true);
    }

    #[Test]
    public function rule_and_article_type_routes_share_the_protected_workbench_guards(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(static fn ($route): bool => str_starts_with((string) $route->getName(), 'admin.writing-rules.')
                || str_starts_with((string) $route->getName(), 'admin.article-types.'));

        $this->assertNotEmpty($routes);

        foreach ($routes as $route) {
            $this->assertContains('admin.super', $route->gatherMiddleware(), $route->getName());
            $this->assertContains('content.production.enabled', $route->gatherMiddleware(), $route->getName());
        }
    }

    #[Test]
    public function only_super_admins_can_open_rule_management_when_the_pipeline_is_enabled(): void
    {
        $this->actingAs($this->admin('admin'), 'admin')
            ->get(route('admin.writing-rules.index'))
            ->assertForbidden();

        config()->set('geoflow.content_production_pipeline_enabled', false);

        $this->actingAs($this->admin('super_admin', 'disabled-super'), 'admin')
            ->get(route('admin.writing-rules.index'))
            ->assertNotFound();
    }

    #[Test]
    public function a_rule_is_created_with_an_immutable_first_version_and_escaped_output(): void
    {
        $admin = $this->admin('super_admin');
        $type = $this->articleType($admin);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.writing-rules.store'), $this->rulePayload($type, [
                'name' => '<script>alert("rule")</script>',
            ]))
            ->assertRedirect();

        $rule = WritingRule::query()->firstOrFail();
        $version = $rule->versions()->firstOrFail();

        $this->assertSame(1, $rule->current_version);
        $this->assertSame('zh_CN', $version->settings['language']);
        $this->assertSame(1200, $version->settings['min_words']);
        $this->assertSame(64, strlen($version->settings_hash));

        $this->actingAs($admin, 'admin')
            ->get(route('admin.writing-rules.index'))
            ->assertOk()
            ->assertSeeText('<script>alert("rule")</script>')
            ->assertDontSee('<script>alert("rule")</script>', false);
    }

    #[Test]
    public function every_update_creates_a_new_version_without_changing_history(): void
    {
        $admin = $this->admin('super_admin');
        $type = $this->articleType($admin);
        $service = app(WritingRuleVersionService::class);
        $rule = $service->create($admin, $this->rulePayload($type));
        $original = $rule->versions()->where('version', 1)->firstOrFail()->settings;

        $service->update($admin, $rule, $this->rulePayload($type, [
            'min_words' => 1800,
            'max_words' => 2600,
            'change_note' => '提高文章深度',
        ]));

        $rule->refresh();
        $this->assertSame(2, $rule->current_version);
        $this->assertCount(2, $rule->versions);
        $this->assertSame($original, $rule->versions()->where('version', 1)->firstOrFail()->settings);
        $this->assertSame(1800, $rule->versions()->where('version', 2)->firstOrFail()->settings['min_words']);
    }

    #[Test]
    public function production_keeps_the_original_rule_snapshot_after_the_rule_changes(): void
    {
        $admin = $this->admin('super_admin');
        $type = $this->articleType($admin);
        $service = app(WritingRuleVersionService::class);
        $rule = $service->create($admin, $this->rulePayload($type));
        [$categoryId, $authorId] = $this->ownership();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.content-productions.store'), [
                'idempotency_key' => (string) Str::uuid(),
                'name' => '快照验证',
                'topic' => '企业如何建设内容生产体系',
                'mode' => 'guided',
                'language' => 'zh_CN',
                'writing_rule_id' => $rule->id,
                'category_id' => $categoryId,
                'author_id' => $authorId,
                'target_platforms' => ['wordpress'],
            ])
            ->assertRedirect();

        $production = ContentProduction::query()->firstOrFail();
        $originalSnapshot = $production->writing_rule_snapshot;

        $service->update($admin, $rule, $this->rulePayload($type, [
            'tone' => 'friendly',
            'min_words' => 2000,
            'max_words' => 3000,
        ]));

        $production->refresh();
        $this->assertSame(1, $production->writing_rule_snapshot['version']);
        $this->assertSame('professional', $production->writing_rule_snapshot['settings']['tone']);
        $this->assertSame(1200, $production->writing_rule_snapshot['settings']['min_words']);
        $this->assertSame('official_brand', $production->writing_rule_snapshot['settings']['publisher_identity']);
        $this->assertSame('https://example.com/product', $production->writing_rule_snapshot['settings']['internal_links'][0]['url']);
        $this->assertSame($originalSnapshot, $production->writing_rule_snapshot);
    }

    #[Test]
    public function contradictory_rule_options_are_rejected_before_saving(): void
    {
        $admin = $this->admin('super_admin');
        $type = $this->articleType($admin);

        $this->actingAs($admin, 'admin')
            ->from(route('admin.writing-rules.create'))
            ->post(route('admin.writing-rules.store'), $this->rulePayload($type, [
                'include_citations' => '1',
                'include_external_links' => '0',
                'knowledge_base_ids' => [],
                'include_cta' => '1',
                'cta_text' => '',
            ]))
            ->assertRedirect(route('admin.writing-rules.create'))
            ->assertSessionHasErrors(['include_citations', 'cta_text']);

        $this->assertDatabaseCount('writing_rules', 0);
    }

    #[Test]
    public function official_brand_rules_require_brand_and_same_site_internal_links(): void
    {
        $admin = $this->admin('super_admin');
        $type = $this->articleType($admin);

        $this->actingAs($admin, 'admin')
            ->from(route('admin.writing-rules.create'))
            ->post(route('admin.writing-rules.store'), $this->rulePayload($type, [
                'brand_name' => '',
                'internal_links' => "产品介绍|https://other.example/product",
            ]))
            ->assertRedirect(route('admin.writing-rules.create'))
            ->assertSessionHasErrors(['brand_name', 'internal_links.0.url']);

        $this->assertDatabaseCount('writing_rules', 0);
    }

    #[Test]
    public function inactive_rules_cannot_be_selected_for_new_productions(): void
    {
        $admin = $this->admin('super_admin');
        $type = $this->articleType($admin);
        $rule = app(WritingRuleVersionService::class)->create($admin, $this->rulePayload($type, ['is_active' => false]));

        $this->actingAs($admin, 'admin')
            ->post(route('admin.content-productions.store'), [
                'idempotency_key' => (string) Str::uuid(),
                'name' => '不可创建',
                'topic' => '停用规则测试',
                'mode' => 'guided',
                'language' => 'zh_CN',
                'writing_rule_id' => $rule->id,
            ])
            ->assertSessionHasErrors('writing_rule_id');

        $this->assertDatabaseCount('content_productions', 0);
    }

    #[Test]
    public function system_presets_install_eight_article_types_idempotently(): void
    {
        $admin = $this->admin('super_admin');

        $this->actingAs($admin, 'admin')->post(route('admin.writing-rules.install-presets'))->assertRedirect();
        $this->actingAs($admin, 'admin')->post(route('admin.writing-rules.install-presets'))->assertRedirect();

        $this->assertDatabaseCount('article_types', 8);
        $this->assertDatabaseCount('writing_rules', 8);
        $this->assertDatabaseCount('writing_rule_versions', 8);
        $this->assertSame(8, ArticleType::query()->where('is_system', true)->count());
        $this->assertSame(8, WritingRule::query()->where('is_preset', true)->count());
    }

    #[Test]
    public function guided_and_standard_modes_use_the_same_rule_snapshot(): void
    {
        $admin = $this->admin('super_admin');
        $type = $this->articleType($admin);
        $rule = app(WritingRuleVersionService::class)->create($admin, $this->rulePayload($type));
        [$categoryId, $authorId] = $this->ownership();

        foreach (['guided', 'standard'] as $mode) {
            $this->actingAs($admin, 'admin')->post(route('admin.content-productions.store'), [
                'idempotency_key' => (string) Str::uuid(),
                'name' => $mode,
                'topic' => '统一规则解释器',
                'mode' => $mode,
                'language' => 'zh_CN',
                'writing_rule_id' => $rule->id,
                'category_id' => $categoryId,
                'author_id' => $authorId,
            ])->assertRedirect();
        }

        $snapshots = ContentProduction::query()->orderBy('id')->pluck('writing_rule_snapshot');
        $this->assertCount(2, $snapshots);
        $this->assertSame($snapshots[0], $snapshots[1]);
    }

    #[Test]
    public function system_article_types_cannot_be_edited(): void
    {
        $admin = $this->admin('super_admin');
        $type = $this->articleType($admin, ['is_system' => true]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.article-types.edit', $type))
            ->assertForbidden();
    }

    private function admin(string $role, string $username = 'writing-rule-admin'): Admin
    {
        return Admin::query()->create([
            'username' => $username,
            'password' => 'secret-123',
            'email' => $username.'@example.com',
            'display_name' => 'Writing Rule Admin',
            'role' => $role,
            'status' => 'active',
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function articleType(Admin $admin, array $overrides = []): ArticleType
    {
        return ArticleType::query()->create(array_merge([
            'code' => 'blog',
            'name' => '博客文章',
            'description' => '常规博客内容',
            'default_settings' => ['min_words' => 1000, 'max_words' => 2200],
            'is_system' => false,
            'is_active' => true,
            'created_by_admin_id' => $admin->id,
        ], $overrides));
    }

    /** @return array{int,int} */
    private function ownership(): array
    {
        $category = Category::query()->create(['name' => '内容生产', 'slug' => 'content-production']);
        $author = Author::query()->create(['name' => '品牌编辑', 'slug' => 'brand-editor']);

        return [$category->id, $author->id];
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function rulePayload(ArticleType $type, array $overrides = []): array
    {
        return array_merge([
            'name' => '中文专业博客',
            'description' => '用于中文网站文章',
            'article_type_id' => $type->id,
            'language' => 'zh_CN',
            'country' => 'CN',
            'tone' => 'professional',
            'perspective' => 'auto',
            'publisher_identity' => 'official_brand',
            'brand_name' => '示例品牌',
            'official_site_url' => 'https://example.com',
            'formality' => 'formal',
            'creativity' => 30,
            'min_words' => 1200,
            'max_words' => 2200,
            'min_headings' => 5,
            'max_headings' => 8,
            'include_citations' => true,
            'include_internal_links' => true,
            'internal_links' => [
                ['anchor' => '产品介绍', 'url' => 'https://example.com/product'],
            ],
            'include_external_links' => true,
            'include_faq' => true,
            'include_cta' => false,
            'cta_text' => null,
            'knowledge_base_ids' => [],
            'sensitive_word_ids' => [],
            'brand_profile' => '示例品牌资料',
            'instructions' => '使用清晰的中文表达。',
            'change_note' => null,
            'is_active' => true,
        ], $overrides);
    }
}
