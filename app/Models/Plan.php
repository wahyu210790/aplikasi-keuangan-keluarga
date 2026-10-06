<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'duration_days',
        'max_members',
        'max_accounts',
        'max_transactions_per_month',
        // is_active defaults via $attributes, not mass assignable
    ];

    /**
     * The model's default values for attributes.
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'price' => 'decimal:2',
        'duration_days' => 'integer',
        'max_members' => 'integer',
        'max_accounts' => 'integer',
        'max_transactions_per_month' => 'integer',
        'is_active' => 'boolean',
    ];
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

}

