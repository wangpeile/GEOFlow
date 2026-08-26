<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->foreignId('content_topic_id')->nullable()->after('writing_rule_version_id')->constrained()->nullOnDelete();
            $table->string('production_time', 5)->default('00:05')->after('automation_timezone');
            $table->index(['content_topic_id', 'schedule_enabled'], 'tasks_content_topic_plan_index');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropIndex('tasks_content_topic_plan_index');
            $table->dropConstrainedForeignId('content_topic_id');
            $table->dropColumn('production_time');
        });
    }
};
