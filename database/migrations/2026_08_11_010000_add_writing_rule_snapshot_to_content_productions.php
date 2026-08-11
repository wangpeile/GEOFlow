<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_productions', function (Blueprint $table): void {
            $table->foreignId('writing_rule_id')->nullable()->after('created_by_admin_id')->constrained()->nullOnDelete();
            $table->foreignId('writing_rule_version_id')->nullable()->after('writing_rule_id')->constrained()->nullOnDelete();
            $table->json('writing_rule_snapshot')->nullable()->after('target_platforms');
        });
    }

    public function down(): void
    {
        Schema::table('content_productions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('writing_rule_version_id');
            $table->dropConstrainedForeignId('writing_rule_id');
            $table->dropColumn('writing_rule_snapshot');
        });
    }
};
