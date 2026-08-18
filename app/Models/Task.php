<?php

namespace App\Models;

use App\Enums\TaskPipelineMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    protected $table = 'tasks';

    protected $attributes = [
        'pipeline_mode' => 'legacy',
        'automation_timezone' => 'Asia/Shanghai',
        'daily_production_limit' => 1,
        'max_production_concurrency' => 1,
        'production_failure_policy' => 'continue',
        'production_output_policy' => 'wordpress_draft',
        'auto_publish_enabled' => false,
    ];

    protected $fillable = [
        'name',
        'title_library_id',
        'image_library_id',
        'image_count',
        'prompt_id',
        'ai_model_id',
        'author_id',
        'need_review',
        'publish_interval',
        'author_type',
        'custom_author_id',
        'auto_keywords',
        'auto_description',
        'draft_limit',
        'article_limit',
        'is_loop',
        'model_selection_mode',
        'status',
        'publish_scope',
        'distribution_strategy',
        'distribution_cursor',
        'created_count',
        'published_count',
        'loop_count',
        'knowledge_base_id',
        'category_mode',
        'fixed_category_id',
        'last_run_at',
        'next_run_at',
        'next_publish_at',
        'last_success_at',
        'last_error_at',
        'last_error_message',
        'schedule_enabled',
        'max_retry_count',
        'created_by_admin_id',
        'pipeline_mode',
        'writing_rule_id',
        'writing_rule_version_id',
        'automation_timezone',
        'daily_production_limit',
        'max_production_concurrency',
        'production_failure_policy',
        'production_output_policy',
        'auto_publish_enabled',
        'daily_token_budget',
        'automation_settings',
    ];

    protected function casts(): array
    {
        return [
            'title_library_id' => 'integer',
            'image_library_id' => 'integer',
            'image_count' => 'integer',
            'prompt_id' => 'integer',
            'ai_model_id' => 'integer',
            'author_id' => 'integer',
            'need_review' => 'integer',
            'publish_interval' => 'integer',
            'custom_author_id' => 'integer',
            'auto_keywords' => 'integer',
            'auto_description' => 'integer',
            'draft_limit' => 'integer',
            'article_limit' => 'integer',
            'is_loop' => 'integer',
            'distribution_cursor' => 'integer',
            'created_count' => 'integer',
            'published_count' => 'integer',
            'loop_count' => 'integer',
            'knowledge_base_id' => 'integer',
            'fixed_category_id' => 'integer',
            'last_run_at' => 'datetime',
            'next_run_at' => 'datetime',
            'next_publish_at' => 'datetime',
            'last_success_at' => 'datetime',
            'last_error_at' => 'datetime',
            'schedule_enabled' => 'integer',
            'max_retry_count' => 'integer',
            'created_by_admin_id' => 'integer',
            'pipeline_mode' => TaskPipelineMode::class,
            'writing_rule_id' => 'integer',
            'writing_rule_version_id' => 'integer',
            'daily_production_limit' => 'integer',
            'max_production_concurrency' => 'integer',
            'auto_publish_enabled' => 'boolean',
            'daily_token_budget' => 'integer',
            'automation_settings' => 'array',
        ];
    }

    public function titleLibrary(): BelongsTo
    {
        return $this->belongsTo(TitleLibrary::class, 'title_library_id');
    }

    public function imageLibrary(): BelongsTo
    {
        return $this->belongsTo(ImageLibrary::class, 'image_library_id');
    }

    public function prompt(): BelongsTo
    {
        return $this->belongsTo(Prompt::class, 'prompt_id');
    }

    public function aiModel(): BelongsTo
    {
        return $this->belongsTo(AiModel::class, 'ai_model_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class, 'author_id');
    }

    public function customAuthor(): BelongsTo
    {
        return $this->belongsTo(Author::class, 'custom_author_id');
    }

    public function knowledgeBase(): BelongsTo
    {
        return $this->belongsTo(KnowledgeBase::class, 'knowledge_base_id');
    }

    public function knowledgeBases(): BelongsToMany
    {
        return $this->belongsToMany(KnowledgeBase::class, 'task_knowledge_bases')
            ->withPivot(['sort_order'])
            ->withTimestamps()
            ->orderByPivot('sort_order')
            ->orderBy('knowledge_bases.id');
    }

    public function fixedCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'fixed_category_id');
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class, 'task_id');
    }

    public function taskSchedules(): HasMany
    {
        return $this->hasMany(TaskSchedule::class, 'task_id');
    }

    public function taskRuns(): HasMany
    {
        return $this->hasMany(TaskRun::class, 'task_id');
    }

    public function contentProductions(): HasMany
    {
        return $this->hasMany(ContentProduction::class);
    }

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    public function writingRule(): BelongsTo
    {
        return $this->belongsTo(WritingRule::class);
    }

    public function writingRuleVersion(): BelongsTo
    {
        return $this->belongsTo(WritingRuleVersion::class);
    }

    public function automationRuns(): HasMany
    {
        return $this->hasMany(ContentAutomationRun::class);
    }

    public function distributionChannels(): BelongsToMany
    {
        return $this->belongsToMany(DistributionChannel::class, 'task_distribution_channels')
            ->withPivot(['trigger', 'remote_status', 'failure_policy', 'max_attempts', 'sort_order'])
            ->withTimestamps()
            ->orderByPivot('sort_order')
            ->orderBy('distribution_channels.id');
    }
}
