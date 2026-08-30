<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_platform_specifications', function (Blueprint $table): void {
            $table->id();
            $table->string('platform', 80)->unique();
            $table->string('label', 100);
            $table->string('version', 40);
            $table->string('status', 30)->default('active');
            $table->string('source_url', 2048)->nullable();
            $table->text('source_summary')->nullable();
            $table->json('rules')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('next_review_at')->nullable();
            $table->timestamps();
        });

        Schema::table('content_variants', function (Blueprint $table): void {
            $table->foreignId('content_platform_specification_id')->nullable()->after('platform')->constrained()->nullOnDelete();
            $table->string('platform_specification_version', 40)->nullable()->after('template_version');
            $table->json('publication_payload')->nullable()->after('quality_check');
            $table->json('publication_readiness')->nullable()->after('publication_payload');
        });

        Schema::table('content_variant_versions', function (Blueprint $table): void {
            $table->json('publication_payload')->nullable()->after('quality_check');
        });
    }

    public function down(): void
    {
        Schema::table('content_variant_versions', function (Blueprint $table): void {
            $table->dropColumn('publication_payload');
        });
        Schema::table('content_variants', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('content_platform_specification_id');
            $table->dropColumn(['platform_specification_version', 'publication_payload', 'publication_readiness']);
        });
        Schema::dropIfExists('content_platform_specifications');
    }
};
