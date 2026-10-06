<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\Subscription;
use App\Models\Transaction;

class QuotaEnforcementService
{
    /**
     * Get active subscription for household.
     */
    public static function getActiveSubscription(Household $household): ?Subscription
    {
        return Subscription::with('plan')
            ->where('household_id', $household->id)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();
    }

    /**
     * Check if household can add more members.
     */
    public static function canAddMember(Household $household): bool
    {
        $sub = self::getActiveSubscription($household);
        if (!$sub || !$sub->plan) {
            return true; // No quota restriction if no plan assigned
        }

        $max = $sub->plan->max_members;
        if (is_null($max) || $max <= 0) {
            return true; // Unlimited
        }

        $count = HouseholdMember::where('household_id', $household->id)->count();
        return $count < $max;
    }

    /**
     * Check if household can add more accounts.
     */
    public static function canAddAccount(Household $household): bool
    {
        $sub = self::getActiveSubscription($household);
        if (!$sub || !$sub->plan) {
            return true;
        }

        $max = $sub->plan->max_accounts;
        if (is_null($max) || $max <= 0) {
            return true;
        }

        $count = Account::where('household_id', $household->id)->count();
        return $count < $max;
    }

    /**
     * Check if household can add more transactions this month.
     */
    public static function canAddTransaction(Household $household): bool
    {
        $sub = self::getActiveSubscription($household);
        if (!$sub || !$sub->plan) {
            return true;
        }

        $max = $sub->plan->max_transactions_per_month;
        if (is_null($max) || $max <= 0) {
            return true;
        }

        $startOfMonth = now()->startOfMonth()->toDateString();
        $endOfMonth = now()->endOfMonth()->toDateString();

        $count = Transaction::where('household_id', $household->id)
            ->whereBetween('transaction_date', [$startOfMonth, $endOfMonth])
            ->count();

        return $count < $max;
    }

    /**
     * Get usage statistics for household.
     */
    public static function getUsageStats(Household $household): array
    {
        $sub = self::getActiveSubscription($household);
        $plan = $sub ? $sub->plan : null;

        $membersCount = HouseholdMember::where('household_id', $household->id)->count();
        $accountsCount = Account::where('household_id', $household->id)->count();

        $startOfMonth = now()->startOfMonth()->toDateString();
        $endOfMonth = now()->endOfMonth()->toDateString();
        $transactionsCount = Transaction::where('household_id', $household->id)
            ->whereBetween('transaction_date', [$startOfMonth, $endOfMonth])
            ->count();

        return [
            'subscription' => $sub,
            'plan' => $plan,
            'usage' => [
                'members' => [
                    'current' => $membersCount,
                    'max' => $plan ? $plan->max_members : null,
                ],
                'accounts' => [
                    'current' => $accountsCount,
                    'max' => $plan ? $plan->max_accounts : null,
                ],
                'transactions_this_month' => [
                    'current' => $transactionsCount,
                    'max' => $plan ? $plan->max_transactions_per_month : null,
                ],
            ]
        ];
    }
}
