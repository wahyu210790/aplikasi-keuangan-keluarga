<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Household;
use App\Models\HouseholdMember;

class HouseholdPolicy
{
    /**
     * Determine if the given user can view the household.
     */
    public function view(User $user, Household $household): bool
    {
        // Any member (owner or member) can view.
        return HouseholdMember::where('user_id', $user->id)
            ->where('household_id', $household->id)
            ->exists();
    }

    /**
     * Determine if the given user can update the household.
     */
    public function update(User $user, Household $household): bool
    {
        $membership = HouseholdMember::where('user_id', $user->id)
            ->where('household_id', $household->id)
            ->first();
        return $membership && $membership->role === 'household_owner';
    }

    /**
     * Determine if the given user can delete the household.
     */
    public function delete(User $user, Household $household): bool
    {
        $membership = HouseholdMember::where('user_id', $user->id)
            ->where('household_id', $household->id)
            ->first();
        return $membership && $membership->role === 'household_owner';
    }
}
