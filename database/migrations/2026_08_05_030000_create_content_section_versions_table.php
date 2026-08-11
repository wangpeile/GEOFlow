<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_section_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('content_production_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outline_version_id')->constrained('content_direction_versions')->cascadeOnDelete();
            $table->uuid('section_key');
            $table->string('heading', 500);
            $table->string('level', 8);
            $table->unsignedSmallInteger('position');
            $table->unsignedInteger('version');
            $table->string('status', 24)->default('pending');
            $table->longText('content')->nullable();
            $table->json('evidence_ids')->nullable();
            $table->char('input_hash', 64);
            $table->string('model', 120)->nullable();
            $table->string('prompt_version', 32)->nullable();
            $table->string('generation_source', 32)->default('manual');
            $table->text('error_message')->nullable();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['content_production_id', 'section_key', 'version'],
                'content_section_version_unique'
            );
            $table->index(
                ['content_production_id', 'outline_version_id', 'position'],
                'content_section_outline_position_index'
            );
            $table->index(
                ['content_production_id', 'status'],
                'content_section_status_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_section_versions');
    }
};
