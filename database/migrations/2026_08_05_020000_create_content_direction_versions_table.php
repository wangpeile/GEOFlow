<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_direction_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('content_production_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 24);
            $table->unsignedInteger('version');
            $table->json('payload');
            $table->char('input_hash', 64);
            $table->json('source_version_ids')->nullable();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('confirmed_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('invalidated_at')->nullable();
            $table->string('invalidation_reason', 180)->nullable();
            $table->timestamps();

            $table->unique(
                ['content_production_id', 'kind', 'version'],
                'content_direction_kind_version_unique'
            );
            $table->index(
                ['content_production_id', 'kind', 'confirmed_at'],
                'content_direction_confirmed_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_direction_versions');
    }
};
