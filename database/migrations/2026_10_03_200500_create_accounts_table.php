<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('household_id');
            $table->string('name', 255);
            $table->enum('type', ['bank', 'cash', 'e_wallet']);
            $table->decimal('initial_balance', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Indexes
            $table->index('household_id');
            $table->index(['household_id', 'is_active']);

            // Foreign key
            $table->foreign('household_id')
                  ->references('id')->on('households')
                  ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
