<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('household_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained('households')->cascadeOnDelete();
            $table->string('email');
            $table->string('code')->unique();
            $table->enum('role', ['household_owner', 'household_member'])->default('household_member');
            $table->dateTime('expires_at');
            $table->enum('status', ['pending', 'accepted', 'expired', 'cancelled'])->default('pending');
            $table->timestamps();

            $table->index('household_id');
            $table->index('code');
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('household_invitations');
    }
};
