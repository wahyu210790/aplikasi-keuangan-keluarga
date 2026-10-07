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
        Schema::table('accounts', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->after('household_id');

            $table->index(['household_id', 'user_id']);
            $table->index(['user_id', 'is_active']);

            $table->foreign('user_id')
                  ->references('id')->on('users')
                  ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropIndex(['household_id', 'user_id']);
            $table->dropIndex(['user_id', 'is_active']);
            $table->dropColumn('user_id');
        });
    }
};
