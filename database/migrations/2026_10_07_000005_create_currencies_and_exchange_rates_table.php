<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 3)->unique();
            $table->string('name');
            $table->string('symbol', 10);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->string('from_currency', 3);
            $table->string('to_currency', 3);
            $table->decimal('rate', 15, 6);
            $table->date('effective_date');
            $table->timestamps();

            $table->index(['from_currency', 'to_currency']);
            $table->index('effective_date');
        });

        // Seed default baseline currencies
        DB::table('currencies')->insert([
            ['code' => 'IDR', 'name' => 'Rupiah Indonesia', 'symbol' => 'Rp', 'is_active' => true, 'is_default' => true, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'USD', 'name' => 'Dolar Amerika', 'symbol' => '$', 'is_active' => true, 'is_default' => false, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'is_active' => true, 'is_default' => false, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Seed default exchange rates
        DB::table('exchange_rates')->insert([
            ['from_currency' => 'USD', 'to_currency' => 'IDR', 'rate' => 15500.000000, 'effective_date' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now()],
            ['from_currency' => 'IDR', 'to_currency' => 'USD', 'rate' => 0.000065, 'effective_date' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now()],
            ['from_currency' => 'EUR', 'to_currency' => 'IDR', 'rate' => 16800.000000, 'effective_date' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now()],
            ['from_currency' => 'IDR', 'to_currency' => 'EUR', 'rate' => 0.000060, 'effective_date' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
        Schema::dropIfExists('currencies');
    }
};
