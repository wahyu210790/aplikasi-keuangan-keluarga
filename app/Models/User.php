<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'global_role',    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Household members belonging to this user.
     */
    public function householdMembers()
    {
        return $this->hasMany(\App\Models\HouseholdMember::class);
    }

    /**
     * Households this user belongs to (through household_members).
     */
    public function households()
    {
        return $this->belongsToMany(\App\Models\Household::class, 'household_members')
                    ->withPivot('role')
                    ->withTimestamps();
    }

    public function activityLogs()
    {
        return $this->hasMany(\App\Models\ActivityLog::class);
    }
    /**
    * Determine if the user has the super admin role.
    */
    public function isSuperAdmin(): bool
    {
        return $this->global_role === 'super_admin';
    }

}
