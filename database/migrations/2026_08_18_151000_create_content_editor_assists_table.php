<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_editor_assists', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('action', 30);
            $table->string('selection_hash', 64);
            $table->longText('source_text');
            $table->text('instruction')->nullable();
            $table->longText('result_text');
            $table->string('model')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['article_id', 'created_at'], 'content_editor_assists_article_created_idx');
            $table->index(['action', 'created_at'], 'content_editor_assists_action_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_editor_assists');
    }
};
