<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'household_id',
        'name',
        'type',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'household_id' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Get the household that owns the category.
     */
    public function household()
    {
        return $this->belongsTo(Household::class);
    }
}
