<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the plan seeds.
     */
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Free',
                'slug' => 'free',
                'description' => 'Paket gratis untuk mencoba aplikasi',
                'price' => 0,
                'duration_days' => 30,
                'max_members' => 2,
                'max_accounts' => 3,
                'max_transactions_per_month' => 100,
                'is_active' => true,
            ],
            [
                'name' => 'Basic',
                'slug' => 'basic',
                'description' => 'Paket dasar untuk pengelolaan keuangan keluarga',
                'price' => 49000,
                'duration_days' => 30,
                'max_members' => 5,
                'max_accounts' => 10,
                'max_transactions_per_month' => 500,
                'is_active' => true,
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'description' => 'Paket untuk keluarga dengan kebutuhan pengelolaan keuangan lebih besar',
                'price' => 99000,
                'duration_days' => 30,
                'max_members' => 10,
                'max_accounts' => 20,
                'max_transactions_per_month' => 2000,
                'is_active' => true,
            ],
        ];

        foreach ($plans as $data) {
            Plan::updateOrCreate(
                ['slug' => $data['slug']],
                $data
            );
        }
    }
}
