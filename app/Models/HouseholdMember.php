<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HouseholdMember extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'household_id',
        'role',
    ];

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
