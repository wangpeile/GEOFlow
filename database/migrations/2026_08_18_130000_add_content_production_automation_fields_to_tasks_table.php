<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('pipeline_mode', 32)->default('legacy')->index();
            $table->foreignId('writing_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('writing_rule_version_id')->nullable()->constrained()->nullOnDelete();
            $table->string('automation_timezone', 64)->default('Asia/Shanghai');
            $table->unsignedSmallInteger('daily_production_limit')->default(1);
            $table->unsignedSmallInteger('max_production_concurrency')->default(1);
            $table->string('production_failure_policy', 20)->default('continue');
            $table->string('production_output_policy', 32)->default('wordpress_draft');
            $table->boolean('auto_publish_enabled')->default(false);
            $table->unsignedBigInteger('daily_token_budget')->nullable();
            $table->json('automation_settings')->nullable();
            $table->index(['status', 'schedule_enabled', 'next_run_at'], 'tasks_automation_due_index');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropIndex('tasks_automation_due_index');
            $table->dropConstrainedForeignId('created_by_admin_id');
            $table->dropConstrainedForeignId('writing_rule_id');
            $table->dropConstrainedForeignId('writing_rule_version_id');
            $table->dropColumn([
                'pipeline_mode', 'automation_timezone', 'daily_production_limit',
                'max_production_concurrency', 'production_failure_policy',
                'production_output_policy', 'auto_publish_enabled', 'daily_token_budget',
                'automation_settings',
            ]);
        });
    }
};
