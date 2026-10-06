<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('household_id')->nullable()->constrained('households')->nullOnDelete();
            $table->string('action');
            $table->string('entity_type')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            // Indexes for common queries
            $table->index('user_id');
            $table->index('household_id');
            $table->index('action');
            $table->index('created_at');
            $table->index(['entity_type', 'entity_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
