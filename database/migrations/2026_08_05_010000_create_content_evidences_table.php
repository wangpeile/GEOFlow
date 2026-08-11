<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_evidences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('content_production_id')->constrained()->cascadeOnDelete();
            $table->foreignId('knowledge_base_id')->nullable()->constrained('knowledge_bases')->nullOnDelete();
            $table->foreignId('knowledge_chunk_id')->nullable()->constrained('knowledge_chunks')->nullOnDelete();
            $table->foreignId('url_import_job_id')->nullable()->constrained('url_import_jobs')->nullOnDelete();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('source_type', 30);
            $table->string('usage', 30)->default('reference_only');
            $table->string('source_key', 191);
            $table->text('source_url')->nullable();
            $table->string('source_title')->nullable();
            $table->longText('content_snapshot');
            $table->text('excerpt')->nullable();
            $table->decimal('confidence', 6, 5)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('collected_at')->nullable();
            $table->timestamps();

            $table->unique(['content_production_id', 'source_key'], 'content_evidence_production_source_unique');
            $table->index(['content_production_id', 'usage']);
            $table->index(['source_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_evidences');
    }
};
