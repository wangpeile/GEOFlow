<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_variant_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('content_variant_id')->constrained('content_variants')->cascadeOnDelete();
            $table->foreignId('content_variant_version_id')->nullable()->constrained('content_variant_versions')->nullOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('decision', 20);
            $table->text('note')->nullable();
            $table->json('quality_check')->nullable();
            $table->timestamps();

            $table->index(['content_variant_id', 'created_at']);
            $table->index(['decision', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_variant_reviews');
    }
};
