<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_productions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('idempotency_key', 64)->nullable()->unique();
            $table->foreignId('task_id')->nullable()->constrained('tasks')->nullOnDelete();
            $table->foreignId('article_id')->nullable()->constrained('articles')->nullOnDelete();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('name');
            $table->string('topic', 500);
            $table->string('mode', 32);
            $table->string('status', 32);
            $table->string('current_stage', 64)->nullable();
            $table->string('language', 16)->default('zh_CN');
            $table->json('target_platforms')->nullable();
            $table->json('context')->nullable();
            $table->string('failure_type', 32)->nullable();
            $table->text('last_error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['task_id', 'status']);
            $table->index(['current_stage', 'status']);
        });

        Schema::create('content_stage_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('content_production_id')->constrained()->cascadeOnDelete();
            $table->string('stage', 64);
            $table->string('status', 32);
            $table->unsignedSmallInteger('sequence');
            $table->unsignedInteger('attempt')->default(1);
            $table->unsignedSmallInteger('contract_version')->default(1);
            $table->char('input_hash', 64)->nullable();
            $table->json('input_payload')->nullable();
            $table->json('output_payload')->nullable();
            $table->string('model', 120)->nullable();
            $table->string('rule_version', 64)->nullable();
            $table->string('failure_type', 32)->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedBigInteger('duration_ms')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->unique(['content_production_id', 'stage', 'attempt'], 'content_stage_attempt_unique');
            $table->index(['content_production_id', 'status', 'sequence'], 'content_stage_status_sequence_index');
        });

        Schema::create('content_production_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('content_production_id')->constrained()->cascadeOnDelete();
            $table->foreignId('content_stage_run_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('event', 64);
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['content_production_id', 'created_at'], 'content_production_event_timeline_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_production_events');
        Schema::dropIfExists('content_stage_runs');
        Schema::dropIfExists('content_productions');
    }
};
