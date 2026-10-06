<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'household_id',
        'plan_id',
        'status',
        'starts_at',
        'expires_at',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Relationship to the household.
     */
    public function household()
    {
        return $this->belongsTo(Household::class);
    }

    /**
     * Relationship to the plan.
     */
    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }
}
