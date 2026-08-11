<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quality_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('content_production_id')->constrained()->cascadeOnDelete();
            $table->foreignId('article_version_id')->constrained('article_versions')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('status', 24);
            $table->json('issues');
            $table->json('summary');
            $table->char('input_hash', 64);
            $table->string('ruleset_version', 32);
            $table->json('risk_snapshot');
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('checked_at');
            $table->timestamps();

            $table->unique(['content_production_id', 'version'], 'quality_report_production_version_unique');
            $table->unique(['content_production_id', 'input_hash'], 'quality_report_input_unique');
            $table->index(['content_production_id', 'status', 'checked_at'], 'quality_report_status_index');
        });

        Schema::create('quality_repair_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('content_production_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quality_report_id')->constrained('quality_reports')->cascadeOnDelete();
            $table->foreignId('source_article_version_id')->constrained('article_versions')->cascadeOnDelete();
            $table->foreignId('repaired_article_version_id')->nullable()->constrained('article_versions')->nullOnDelete();
            $table->unsignedSmallInteger('attempt');
            $table->string('status', 24);
            $table->json('selected_issue_ids');
            $table->json('result')->nullable();
            $table->text('error_message')->nullable();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->unique(['quality_report_id', 'attempt'], 'quality_repair_report_attempt_unique');
            $table->index(['quality_report_id', 'status'], 'quality_repair_report_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quality_repair_attempts');
        Schema::dropIfExists('quality_reports');
    }
};
