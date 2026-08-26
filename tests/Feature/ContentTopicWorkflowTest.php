<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Author;
use App\Models\Category;
use App\Models\ContentProduction;
use App\Models\ContentTopic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ContentTopicWorkflowTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function super_admin_can_create_a_topic_and_keep_single_article_creation_available(): void
    {
        config()->set('geoflow.content_production_pipeline_enabled', true);
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')->get(route('admin.content-topics.create'))
            ->assertOk()->assertSee('内容专题')->assertSee('新建内容专题');

        $this->actingAs($admin, 'admin')->post(route('admin.content-topics.store'), [
            'name' => '企业视频会议内容专题', 'website' => 'https://example.com',
            'audience' => '中小企业 IT 管理者', 'keyword_clusters' => "视频会议\n远程协作",
            'reference_urls' => 'https://example.com/source', 'is_active' => '1',
        ])->assertRedirect();

        $topic = ContentTopic::query()->firstOrFail();
        $this->assertSame(['视频会议', '远程协作'], $topic->keyword_clusters);

        $this->actingAs($admin, 'admin')->get(route('admin.content-productions.create'))
            ->assertOk()->assertSee('不属于专题');
    }

    #[Test]
    public function creating_from_candidate_idea_snapshots_the_topic_and_schedules_the_idea(): void
    {
        config()->set('geoflow.content_production_pipeline_enabled', true);
        $admin = $this->admin();
        $category = Category::query()->create(['name' => '内容生产', 'slug' => 'content-production']);
        $author = Author::query()->create(['name' => '内容团队']);
        $topic = ContentTopic::query()->create([
            'name' => '企业视频会议内容专题', 'website' => 'https://example.com',
            'keyword_clusters' => ['视频会议'], 'created_by_admin_id' => $admin->id,
        ]);
        $idea = $topic->ideas()->create(['topic' => '企业如何选择视频会议系统', 'status' => 'candidate']);

        $this->actingAs($admin, 'admin')->post(route('admin.content-productions.store'), [
            'idempotency_key' => '10c5c7bb-d260-4ff3-a5a2-e9975281d3e8',
            'name' => '视频会议选型', 'topic' => $idea->topic, 'mode' => 'guided', 'language' => 'zh_CN',
            'category_id' => $category->id, 'author_id' => $author->id, 'target_platforms' => ['wordpress'],
            'content_topic_id' => $topic->id, 'content_topic_idea_id' => $idea->id,
        ])->assertRedirect();

        $production = ContentProduction::query()->firstOrFail();
        $this->assertSame($topic->id, $production->content_topic_id);
        $this->assertSame($idea->id, $production->content_topic_idea_id);
        $this->assertSame('企业视频会议内容专题', data_get($production->context, 'content_topic_snapshot.name'));
        $this->assertSame('scheduled', $idea->fresh()->status);

        $topic->update(['website' => 'https://changed.example.com']);
        $this->assertSame('https://example.com', data_get($production->fresh()->context, 'content_topic_snapshot.website'));
    }

    #[Test]
    public function an_idea_cannot_be_attached_to_another_topic(): void
    {
        config()->set('geoflow.content_production_pipeline_enabled', true);
        $admin = $this->admin();
        $category = Category::query()->create(['name' => '内容生产', 'slug' => 'content-production']);
        $author = Author::query()->create(['name' => '内容团队']);
        $first = ContentTopic::query()->create(['name' => '专题一', 'created_by_admin_id' => $admin->id]);
        $second = ContentTopic::query()->create(['name' => '专题二', 'created_by_admin_id' => $admin->id]);
        $idea = $second->ideas()->create(['topic' => '其他专题选题']);

        $this->actingAs($admin, 'admin')->from(route('admin.content-productions.create'))
            ->post(route('admin.content-productions.store'), [
                'idempotency_key' => '8c59012f-94ad-49a7-bd0b-862293c2b87f', 'name' => '测试', 'topic' => '测试',
                'mode' => 'guided', 'language' => 'zh_CN', 'category_id' => $category->id, 'author_id' => $author->id,
                'content_topic_id' => $first->id, 'content_topic_idea_id' => $idea->id,
            ])->assertSessionHasErrors('content_topic_idea_id');
    }

    private function admin(): Admin
    {
        return Admin::query()->create(['username' => 'topic_admin', 'password' => 'secret-123', 'email' => 'topic@example.com', 'display_name' => 'Topic Admin', 'role' => 'super_admin', 'status' => 'active']);
    }
}
