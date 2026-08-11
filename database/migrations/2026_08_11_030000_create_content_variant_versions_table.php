<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_variant_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('content_variant_id')->constrained('content_variants')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('title', 500);
            $table->text('excerpt')->nullable();
            $table->longText('content');
            $table->json('tags')->nullable();
            $table->json('image_requirements')->nullable();
            $table->string('template_version', 30);
            $table->json('generation_meta')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();

            $table->unique(['content_variant_id', 'version']);
            $table->index(['content_variant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_variant_versions');
    }
};
