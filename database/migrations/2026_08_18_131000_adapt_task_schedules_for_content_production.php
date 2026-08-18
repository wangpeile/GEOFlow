<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_schedules', function (Blueprint $table): void {
            $table->uuid('uuid')->nullable()->unique();
            $table->date('local_date')->nullable();
            $table->unsignedSmallInteger('slot')->nullable();
            $table->string('topic', 255)->nullable();
            $table->string('topic_hash', 64)->nullable();
            $table->foreignId('content_production_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->json('metadata')->nullable();
            $table->unique(['task_id', 'local_date', 'topic_hash'], 'task_schedules_daily_topic_unique');
            $table->index(['status', 'next_run_time'], 'task_schedules_due_index');
            $table->index(['task_id', 'status'], 'task_schedules_task_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('task_schedules', function (Blueprint $table): void {
            $table->dropUnique('task_schedules_daily_topic_unique');
            $table->dropIndex('task_schedules_due_index');
            $table->dropIndex('task_schedules_task_status_index');
            $table->dropConstrainedForeignId('content_production_id');
            $table->dropColumn([
                'uuid', 'local_date', 'slot', 'topic', 'topic_hash', 'attempt_count',
                'started_at', 'finished_at', 'cancelled_at', 'metadata',
            ]);
        });
    }
};
