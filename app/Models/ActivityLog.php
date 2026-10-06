<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'household_id',
        'action',
        'entity_type',
        'entity_id',
        'description',
        'metadata',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'metadata'   => 'array',
        'created_at' => 'datetime',
    ];

    public $timestamps = false; // No updated_at column

    /**
     * Relationship to the user (optional).
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship to the household (optional).
     */
    public function household()
    {
        return $this->belongsTo(Household::class);
    }
}
