<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_research_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('content_production_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('keyword', 500);
            $table->string('status', 30)->default('completed');
            $table->string('source_mode', 30)->default('url_import');
            $table->json('sources');
            $table->json('analysis');
            $table->text('error_message')->nullable();
            $table->timestamp('collected_at');
            $table->timestamps();

            $table->index(['content_production_id', 'collected_at'], 'content_research_production_collected_idx');
            $table->index(['status', 'created_at'], 'content_research_status_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_research_reports');
    }
};
