<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_platform_feedback', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('content_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('outcome', 40);
            $table->json('reasons')->nullable();
            $table->json('manual_adjustments')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->index(['content_variant_id', 'outcome']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_platform_feedback');
    }
};
