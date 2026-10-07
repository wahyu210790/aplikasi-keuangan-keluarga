<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HouseholdMember extends Model
{
    use HasFactory;

    public const ROLE_OWNER = 'household_owner';
    public const ROLE_ADULT = 'adult_member';
    public const ROLE_CHILD = 'child_member';
    public const ROLE_MEMBER_LEGACY = 'household_member';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'household_id',
        'role',
    ];

    /**
     * Check if member is household owner.
     */
    public function isOwner(): bool
    {
        return $this->role === self::ROLE_OWNER;
    }

    /**
     * Check if member is adult member (or legacy household_member).
     */
    public function isAdult(): bool
    {
        return $this->role === self::ROLE_ADULT || $this->role === self::ROLE_MEMBER_LEGACY;
    }

    /**
     * Check if member is child member.
     */
    public function isChild(): bool
    {
        return $this->role === self::ROLE_CHILD;
    }

    /**
     * The user that belongs to the household member.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The household that this member belongs to.
     */
    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }
}
