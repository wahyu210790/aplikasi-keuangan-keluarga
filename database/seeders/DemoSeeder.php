<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    /**
     * Seed initial Super Admin and Demo Household Owner accounts.
     */
    public function run(): void
    {
        // 1. Super Admin (SaaS Operator/Owner ONLY - NOT in any Household)
        $superAdmin = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'global_role' => 'super_admin',
            ]
        );

        // Ensure Super Admin is NOT a member of any household
        HouseholdMember::where('user_id', $superAdmin->id)->delete();

        // 2. Household Owner (Customer - Household Budi)
        $customerBudi = User::updateOrCreate(
            ['email' => 'budi@example.com'],
            [
                'name' => 'Budi Santoso',
                'password' => Hash::make('password'),
                'global_role' => null,
            ]
        );

        // 3. Household Keluarga Budi
        $household = Household::firstOrCreate(
            ['name' => 'Keluarga Budi'],
            [
                'description' => 'Household Keuangan Keluarga Budi',
                'status' => 'active',
            ]
        );

        // 4. Attach Budi as household_owner
        HouseholdMember::updateOrCreate(
            [
                'household_id' => $household->id,
                'user_id' => $customerBudi->id,
            ],
            [
                'role' => 'household_owner',
            ]
        );

        // 5. Active Subscription for Household Budi
        $plan = Plan::where('slug', 'pro')->orWhere('slug', 'family')->first() ?? Plan::first();
        if ($plan) {
            Subscription::firstOrCreate(
                ['household_id' => $household->id],
                [
                    'plan_id' => $plan->id,
                    'status' => 'active',
                    'starts_at' => now(),
                    'expires_at' => now()->addYear(),
                ]
            );
        }

        // 6. Default Accounts for Household Budi
        $bca = Account::where('household_id', $household->id)->where('name', 'Bank BCA')->first();
        if ($bca) {
            $bca->update(['user_id' => $customerBudi->id]);
        } else {
            Account::create([
                'household_id' => $household->id,
                'user_id' => $customerBudi->id,
                'name' => 'Bank BCA',
                'type' => 'bank',
                'initial_balance' => '5000000.00',
                'is_active' => true,
            ]);
        }

        $cash = Account::where('household_id', $household->id)->where('name', 'Dompet Cash')->first();
        if ($cash) {
            $cash->update(['user_id' => $customerBudi->id]);
        } else {
            Account::create([
                'household_id' => $household->id,
                'user_id' => $customerBudi->id,
                'name' => 'Dompet Cash',
                'type' => 'cash',
                'initial_balance' => '1000000.00',
                'is_active' => true,
            ]);
        }
    }
}
