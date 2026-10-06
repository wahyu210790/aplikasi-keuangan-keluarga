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
        Schema::create('savings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained('households')->onDelete('cascade');
            $table->foreignId('account_id')->nullable()->constrained('accounts')->onDelete('set null');
            $table->string('name');
            $table->decimal('target_amount', 15, 2);
            $table->decimal('current_amount', 15, 2)->default(0.00);
            $table->date('target_date')->nullable();
            $table->boolean('is_completed')->default(false);
            $table->timestamps();

            $table->index(['household_id', 'is_completed']);
            $table->index(['household_id', 'account_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('savings');
    }
};
