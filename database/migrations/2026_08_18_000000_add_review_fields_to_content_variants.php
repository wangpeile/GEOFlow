<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_variants', function (Blueprint $table): void {
            $table->json('quality_check')->nullable()->after('fact_check');
            $table->foreignId('reviewed_by')->nullable()->after('review_status')->constrained('admins')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->text('review_note')->nullable()->after('reviewed_at');

            $table->index(['review_status', 'status']);
        });

        Schema::table('content_variant_versions', function (Blueprint $table): void {
            $table->string('change_type', 30)->default('generated')->after('version');
            $table->json('quality_check')->nullable()->after('generation_meta');
        });
    }

    public function down(): void
    {
        Schema::table('content_variant_versions', function (Blueprint $table): void {
            $table->dropColumn(['change_type', 'quality_check']);
        });

        Schema::table('content_variants', function (Blueprint $table): void {
            $table->dropIndex(['review_status', 'status']);
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['quality_check', 'reviewed_at', 'review_note']);
        });
    }
};
