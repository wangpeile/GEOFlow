<?php

namespace Tests\Feature;

use App\Contracts\GeoFlow\ContentEditorAssistant;
use App\Enums\ContentEvidenceSourceType;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Admin;
use App\Models\Article;
use App\Models\Author;
use App\Models\Category;
use App\Models\ContentEditorAssist;
use App\Models\ContentProduction;
use App\Models\ContentResearchReport;
use App\Models\UrlImportJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ContentIterationElevenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('geoflow.content_production_pipeline_enabled', true);
        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    #[Test]
    public function editor_assistant_returns_a_replacement_without_saving_the_article(): void
    {
        $admin = $this->admin('super_admin');
        $article = $this->article('这是一篇保持不变的正文。');
        $this->app->bind(ContentEditorAssistant::class, fn (): ContentEditorAssistant => new class implements ContentEditorAssistant
        {
            public function assist(Article $article, string $action, string $selection, ?string $instruction = null): array
            {
                return ['text' => '这是扩写后的中文内容。', 'model' => 'fake-editor', 'source' => 'test'];
            }
        });

        $response = $this->actingAs($admin, 'admin')->postJson(
            route('admin.articles.editor.assist', $article),
            ['action' => 'expand', 'selection' => '原始选区'],
        );

        $response->assertOk()->assertJsonPath('data.text', '这是扩写后的中文内容。');
        $this->assertSame('这是一篇保持不变的正文。', $article->fresh()->content);
        $this->assertDatabaseHas(ContentEditorAssist::class, [
            'article_id' => $article->id,
            'action' => 'expand',
            'selection_hash' => hash('sha256', '原始选区'),
        ]);
    }

    #[Test]
    public function editor_assistant_is_protected_and_validates_supported_actions(): void
    {
        $article = $this->article('正文');

        $this->actingAs($this->admin('admin'), 'admin')
            ->postJson(route('admin.articles.editor.assist', $article), ['action' => 'expand', 'selection' => '正文'])
            ->assertForbidden();

        $this->actingAs($this->admin('super_admin'), 'admin')
            ->postJson(route('admin.articles.editor.assist', $article), ['action' => 'delete', 'selection' => '正文'])
            ->assertUnprocessable();
    }

    #[Test]
    public function completed_url_imports_create_traceable_research_evidence(): void
    {
        $admin = $this->admin('super_admin');
        $production = ContentProduction::factory()->create(['created_by_admin_id' => $admin->id]);
        $job = UrlImportJob::query()->create([
            'url' => 'https://example.com/research',
            'normalized_url' => 'https://example.com/research',
            'source_domain' => 'example.com',
            'page_title' => '竞品研究文章',
            'status' => 'completed',
            'result_json' => json_encode(['page' => [
                'title' => '竞品研究文章',
                'summary' => '一段可核验摘要',
                'text' => '这是采集并保存的中文证据正文。',
            ]], JSON_UNESCAPED_UNICODE),
            'finished_at' => now(),
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin, 'admin')->post(
            route('admin.content-productions.research.store', $production),
            ['keyword' => '企业内容生产', 'url_import_job_ids' => [$job->id]],
        )->assertRedirect()->assertSessionHas('message', '竞品研究与内容缺口报告已生成。');

        $report = ContentResearchReport::query()->sole();
        $this->assertSame('completed', $report->status);
        $this->assertSame('url_import', $report->source_mode);
        $this->assertDatabaseHas('content_evidences', [
            'content_production_id' => $production->id,
            'url_import_job_id' => $job->id,
            'source_type' => ContentEvidenceSourceType::SerpResearch->value,
            'source_url' => 'https://example.com/research',
        ]);
    }

    #[Test]
    public function operations_metrics_are_available_in_admin_and_scoped_api(): void
    {
        $admin = $this->admin('super_admin');
        $production = ContentProduction::factory()->create([
            'created_by_admin_id' => $admin->id,
            'topic' => '可下钻的运营指标项目',
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.content-operations.index'))
            ->assertOk()
            ->assertSeeText('可下钻的运营指标项目');

        $plainTextToken = $admin->createToken('Iteration 11 metrics', ['content-metrics:read'])->plainTextToken;
        $this->withHeader('Authorization', 'Bearer '.$plainTextToken)
            ->getJson('/api/v1/content-metrics')
            ->assertOk()
            ->assertJsonPath('data.productions.total', 1)
            ->assertJsonPath('data.recent.0.production_id', $production->id);

    }

    private function admin(string $role): Admin
    {
        return Admin::query()->create([
            'username' => Str::lower(Str::random(8)).'_'.$role,
            'password' => 'secret-123',
            'email' => Str::lower(Str::random(8)).'_'.$role.'@example.com',
            'display_name' => 'Content '.$role,
            'role' => $role,
            'status' => 'active',
        ]);
    }

    private function article(string $content): Article
    {
        $category = Category::query()->create([
            'name' => '迭代 11 测试分类 '.Str::random(6),
            'slug' => 'iteration-11-category-'.Str::lower(Str::random(8)),
        ]);
        $author = Author::query()->create(['name' => '迭代 11 测试作者']);

        return Article::query()->create([
            'title' => '测试文章',
            'slug' => 'iteration-11-'.Str::lower(Str::random(8)),
            'content' => $content,
            'category_id' => $category->id,
            'author_id' => $author->id,
            'status' => 'draft',
            'review_status' => 'pending',
        ]);
    }
}
