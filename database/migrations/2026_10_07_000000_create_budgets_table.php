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
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained('households')->onDelete('cascade');
            $table->foreignId('category_id')->nullable()->constrained('categories')->onDelete('set null');
            $table->string('name');
            $table->decimal('amount', 15, 2);
            $table->enum('period_type', ['monthly', 'custom'])->default('monthly');
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['household_id', 'is_active']);
            $table->index(['household_id', 'category_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};
