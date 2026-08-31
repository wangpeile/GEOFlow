<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_article_revision_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('content_production_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_article_version_id')->constrained('article_versions')->cascadeOnDelete();
            $table->foreignId('revised_article_version_id')->nullable()->constrained('article_versions')->nullOnDelete();
            $table->foreignId('quality_report_id')->nullable()->constrained('quality_reports')->nullOnDelete();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->text('feedback');
            $table->json('instruction_snapshot')->nullable();
            $table->string('model')->nullable();
            $table->string('prompt_version', 32)->default('article-revision-v1');
            $table->char('input_hash', 64);
            $table->string('status', 24);
            $table->json('result')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->unique(['content_production_id', 'input_hash'], 'content_revision_request_input_unique');
            $table->index(['content_production_id', 'status', 'created_at'], 'content_revision_request_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_article_revision_requests');
    }
};
