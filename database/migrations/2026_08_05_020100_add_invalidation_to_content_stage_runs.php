<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_stage_runs', function (Blueprint $table): void {
            $table->timestamp('invalidated_at')->nullable()->after('finished_at');
            $table->string('invalidation_reason', 180)->nullable()->after('invalidated_at');
            $table->index(
                ['content_production_id', 'invalidated_at'],
                'content_stage_invalidated_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('content_stage_runs', function (Blueprint $table): void {
            $table->dropIndex('content_stage_invalidated_index');
            $table->dropColumn(['invalidated_at', 'invalidation_reason']);
        });
    }
};
