<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Household extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'description',
        'settings',
        'status',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'settings' => 'array',
    ];
    /**
    * Members belonging to this household.
    */
    public function members()
    {
        return $this->hasMany(\App\Models\HouseholdMember::class);
    }


    public function activityLogs()
    {
        return $this->hasMany(\App\Models\ActivityLog::class);
    }
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

}
