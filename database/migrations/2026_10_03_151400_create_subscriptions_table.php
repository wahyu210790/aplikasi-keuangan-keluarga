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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained('households')->restrictOnDelete();
            $table->foreignId('plan_id')->constrained('plans')->restrictOnDelete();
            $table->enum('status', ['active', 'expired', 'suspended', 'cancelled']);
            $table->dateTime('starts_at');
            $table->dateTime('expires_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            // Indexes for common queries
            $table->index('household_id');
            $table->index('plan_id');
            $table->index('status');
            $table->index('starts_at');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
