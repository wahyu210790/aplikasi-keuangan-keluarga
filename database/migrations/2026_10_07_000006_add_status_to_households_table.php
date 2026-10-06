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
        Schema::table('households', function (Blueprint $table) {
            if (!Schema::hasColumn('households', 'status')) {
                $table->string('status')->default('active')->after('settings');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('households', function (Blueprint $table) {
            if (Schema::hasColumn('households', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
