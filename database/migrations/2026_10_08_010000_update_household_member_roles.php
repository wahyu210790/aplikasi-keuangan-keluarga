<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('household_members')
            ->where('role', 'household_member')
            ->update(['role' => 'adult_member']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('household_members')
            ->where('role', 'adult_member')
            ->update(['role' => 'household_member']);
    }
};
