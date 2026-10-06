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
        Schema::create('categories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('household_id');
            $table->string('name', 255);
            $table->enum('type', ['income', 'expense']);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('household_id')
                ->references('id')
                ->on('households')
                ->onDelete('cascade');

            $table->index('household_id');
            $table->index(['household_id', 'type']);
            $table->index(['household_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
