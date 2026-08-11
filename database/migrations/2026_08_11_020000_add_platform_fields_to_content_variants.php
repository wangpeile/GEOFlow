<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_variants', function (Blueprint $table): void {
            $table->json('tags')->nullable()->after('content');
            $table->json('image_requirements')->nullable()->after('tags');
            $table->uuid('generation_token')->nullable()->after('generation_meta');
            $table->timestamp('generation_started_at')->nullable()->after('generation_token');
            $table->string('source_content_hash', 64)->nullable()->after('generation_token');
            $table->json('fact_check')->nullable()->after('source_content_hash');
            $table->text('failure_message')->nullable()->after('generation_meta');

            $table->index(['status', 'generation_token']);
        });
    }

    public function down(): void
    {
        Schema::table('content_variants', function (Blueprint $table): void {
            $table->dropIndex(['status', 'generation_token']);
            $table->dropColumn([
                'tags', 'image_requirements', 'generation_token', 'generation_started_at', 'source_content_hash',
                'fact_check', 'failure_message',
            ]);
        });
    }
};
