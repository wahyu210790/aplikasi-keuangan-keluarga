<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class HouseholdPermissionService
{
    /**
     * Get member record for a user in a household. Returns null if not a member.
     */
    public static function getMembership(User $user, Household $household): ?HouseholdMember
    {
        return HouseholdMember::where('household_id', $household->id)
            ->where('user_id', $user->id)
            ->first();
    }

    /**
     * Query accounts accessible (viewable/usable) by a given user in a household.
     */
    public static function accessibleAccountsQuery(User $user, Household $household): Builder
    {
        $membership = self::getMembership($user, $household);

        if (! $membership) {
            // Super Admin or Non-member -> Empty set (0 accessible accounts)
            return Account::whereRaw('1 = 0');
        }

        if ($membership->isOwner() || $membership->isAdult()) {
            // Owner and Adult Members can view all household accounts
            return Account::where('household_id', $household->id);
        }

        if ($membership->isChild()) {
            // Child Member can view accounts owned by them or unassigned/assigned
            return Account::where('household_id', $household->id)
                ->where(function (Builder $query) use ($user) {
                    $query->where('user_id', $user->id)
                        ->orWhereNull('user_id');
                });
        }

        return Account::where('household_id', $household->id);
    }

    /**
     * Check if a specific account is viewable / usable for transactions by a given user.
     */
    public static function canAccessAccount(User $user, Household $household, Account $account): bool
    {
        if ($account->household_id !== $household->id) {
            return false;
        }

        $membership = self::getMembership($user, $household);
        if (! $membership) {
            return false;
        }

        if ($membership->isOwner() || $membership->isAdult()) {
            return true;
        }

        if ($membership->isChild()) {
            return $account->user_id === $user->id || $account->user_id === null;
        }

        return false;
    }

    /**
     * Check if a user can manage (edit/archive) a specific account.
     */
    public static function canManageAccount(User $user, Household $household, Account $account): bool
    {
        if ($account->household_id !== $household->id) {
            return false;
        }

        $membership = self::getMembership($user, $household);
        if (! $membership) {
            return false;
        }

        if ($membership->isOwner()) {
            return true;
        }

        if ($membership->isAdult()) {
            return $account->user_id === $user->id || $account->user_id === null;
        }

        if ($membership->isChild()) {
            return $account->user_id === $user->id;
        }

        return false;
    }
}
