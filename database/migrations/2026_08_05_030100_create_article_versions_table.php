<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('content_production_id')->constrained()->cascadeOnDelete();
            $table->foreignId('article_id')->nullable()->constrained('articles')->nullOnDelete();
            $table->unsignedInteger('version');
            $table->string('kind', 24);
            $table->string('title', 500);
            $table->text('summary');
            $table->longText('body');
            $table->json('faq')->nullable();
            $table->string('meta_title', 500);
            $table->text('meta_description');
            $table->json('section_version_ids');
            $table->char('input_hash', 64);
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('synchronized_at')->nullable();
            $table->timestamps();

            $table->unique(['content_production_id', 'version'], 'article_version_production_unique');
            $table->unique(['content_production_id', 'input_hash'], 'article_version_input_unique');
            $table->index(['article_id', 'created_at'], 'article_version_article_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_versions');
    }
};
