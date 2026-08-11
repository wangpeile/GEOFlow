<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_types', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('default_settings')->nullable();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'name']);
        });

        Schema::create('writing_rules', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('article_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_preset')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('current_version')->default(1);
            $table->timestamps();

            $table->index(['is_active', 'updated_at']);
        });

        Schema::create('writing_rule_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('writing_rule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('article_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->unsignedInteger('version');
            $table->json('settings');
            $table->char('settings_hash', 64);
            $table->text('change_note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['writing_rule_id', 'version']);
            $table->index(['writing_rule_id', 'created_at']);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('writing_rule_versions');
        Schema::dropIfExists('writing_rules');
        Schema::dropIfExists('article_types');
    }
};
