<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('article_distributions', function (Blueprint $table): void {
            $table->foreignId('content_group_id')->nullable()->after('article_id')->constrained('content_groups')->nullOnDelete();
            $table->foreignId('content_variant_id')->nullable()->after('content_group_id')->constrained('content_variants')->nullOnDelete();
            $table->foreignId('content_variant_version_id')->nullable()->after('content_variant_id')->constrained('content_variant_versions')->nullOnDelete();
            $table->foreignId('reviewed_by_admin_id')->nullable()->after('content_variant_version_id')->constrained('admins')->nullOnDelete();
            $table->string('publication_mode', 30)->nullable()->after('action');
            $table->timestamp('scheduled_for')->nullable()->after('publication_mode')->index();
            $table->unsignedInteger('published_version')->nullable()->after('scheduled_for');
            $table->timestamp('published_at')->nullable()->after('published_version');
            $table->index(['content_variant_id', 'distribution_channel_id'], 'article_distributions_variant_channel_index');
        });
    }

    public function down(): void
    {
        Schema::table('article_distributions', function (Blueprint $table): void {
            $table->dropIndex('article_distributions_variant_channel_index');
            $table->dropIndex(['scheduled_for']);
            $table->dropConstrainedForeignId('reviewed_by_admin_id');
            $table->dropConstrainedForeignId('content_variant_version_id');
            $table->dropConstrainedForeignId('content_variant_id');
            $table->dropConstrainedForeignId('content_group_id');
            $table->dropColumn(['publication_mode', 'scheduled_for', 'published_version', 'published_at']);
        });
    }
};
