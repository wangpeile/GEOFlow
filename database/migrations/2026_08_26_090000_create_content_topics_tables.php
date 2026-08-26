<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_topics', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('writing_rule_id')->nullable()->constrained('writing_rules')->nullOnDelete();
            $table->string('name');
            $table->string('website', 500)->nullable();
            $table->string('audience', 500)->nullable();
            $table->json('knowledge_base_ids')->nullable();
            $table->json('reference_urls')->nullable();
            $table->text('material_scope')->nullable();
            $table->json('keyword_clusters')->nullable();
            $table->text('content_goal')->nullable();
            $table->string('publishing_cadence', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['is_active', 'updated_at']);
        });

        Schema::create('content_topic_ideas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('content_topic_id')->constrained()->cascadeOnDelete();
            $table->string('topic', 500);
            $table->json('keywords')->nullable();
            $table->string('angle', 500)->nullable();
            $table->string('status', 32)->default('candidate');
            $table->timestamp('scheduled_for')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['content_topic_id', 'status']);
        });

        Schema::table('content_productions', function (Blueprint $table): void {
            $table->foreignId('content_topic_id')->nullable()->after('task_id')->constrained('content_topics')->nullOnDelete();
            $table->foreignId('content_topic_idea_id')->nullable()->after('content_topic_id')->constrained('content_topic_ideas')->nullOnDelete();
            $table->index(['content_topic_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('content_productions', function (Blueprint $table): void {
            $table->dropIndex(['content_topic_id', 'created_at']);
            $table->dropConstrainedForeignId('content_topic_idea_id');
            $table->dropConstrainedForeignId('content_topic_id');
        });
        Schema::dropIfExists('content_topic_ideas');
        Schema::dropIfExists('content_topics');
    }
};
