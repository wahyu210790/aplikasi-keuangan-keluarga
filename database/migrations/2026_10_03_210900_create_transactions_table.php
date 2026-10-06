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
        Schema::create('transactions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('household_id');
            $table->enum('type', ['income', 'expense', 'transfer']);
            $table->decimal('amount', 15, 2);
            $table->string('description', 255)->nullable();
            $table->date('transaction_date');
            $table->unsignedBigInteger('account_id')->nullable();
            $table->unsignedBigInteger('to_account_id')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('household_id');
            $table->index('account_id');
            $table->index('to_account_id');
            $table->index('transaction_date');
            $table->index(['household_id', 'transaction_date']);

            // Foreign keys
            $table->foreign('household_id')
                ->references('id')
                ->on('households')
                ->onDelete('cascade');

            $table->foreign('account_id')
                ->references('id')
                ->on('accounts')
                ->onDelete('restrict');

            $table->foreign('to_account_id')
                ->references('id')
                ->on('accounts')
                ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
?>
