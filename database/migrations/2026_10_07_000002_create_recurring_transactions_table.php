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
        Schema::create('recurring_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained('households')->onDelete('cascade');
            $table->enum('type', ['income', 'expense', 'transfer']);
            $table->decimal('amount', 15, 2);
            $table->string('description', 255)->nullable();
            $table->enum('frequency', ['daily', 'weekly', 'monthly', 'yearly'])->default('monthly');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->date('last_generated_at')->nullable();
            $table->foreignId('account_id')->nullable()->constrained('accounts')->onDelete('restrict');
            $table->foreignId('to_account_id')->nullable()->constrained('accounts')->onDelete('restrict');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['household_id', 'is_active']);
            $table->index(['household_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recurring_transactions');
    }
};
