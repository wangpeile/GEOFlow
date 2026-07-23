<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('main_article_id')->nullable()->unique()->constrained('articles')->nullOnDelete();
            $table->foreignId('task_id')->nullable()->constrained('tasks')->nullOnDelete();
            $table->string('name', 500);
            $table->string('status', 30)->default('draft');
            $table->json('generation_meta')->nullable();
            $table->timestamps();

            $table->index(['status', 'updated_at']);
            $table->index(['task_id', 'created_at']);
        });

        Schema::create('content_variants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('content_group_id')->constrained('content_groups')->cascadeOnDelete();
            $table->foreignId('source_article_id')->nullable()->constrained('articles')->nullOnDelete();
            $table->string('platform', 40);
            $table->string('title', 500)->default('');
            $table->text('excerpt')->nullable();
            $table->longText('content')->nullable();
            $table->string('status', 30)->default('pending');
            $table->string('review_status', 30)->default('pending');
            $table->unsignedInteger('version')->default(1);
            $table->string('template_version', 30)->default('1.0');
            $table->json('generation_meta')->nullable();
            $table->text('published_url')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['content_group_id', 'platform']);
            $table->index(['platform', 'status', 'updated_at']);
            $table->index(['review_status', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_variants');
        Schema::dropIfExists('content_groups');
    }
};
