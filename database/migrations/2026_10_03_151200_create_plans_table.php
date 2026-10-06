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
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            // decimal(total, places) – using 15,2 which accommodates large Rupiah values safely.
            $table->decimal('price', 15, 2);
            $table->integer('duration_days');
            $table->integer('max_members')->nullable();
            $table->integer('max_accounts')->nullable();
            $table->integer('max_transactions_per_month')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
