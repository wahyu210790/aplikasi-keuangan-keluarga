<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Saving extends Model
{
    use HasFactory;

    protected $fillable = [
        'household_id',
        'account_id',
        'name',
        'target_amount',
        'current_amount',
        'target_date',
        'is_completed',
    ];

    protected $casts = [
        'target_amount' => 'decimal:2',
        'current_amount' => 'decimal:2',
        'target_date' => 'date:Y-m-d',
        'is_completed' => 'boolean',
    ];

    protected $appends = [
        'remaining_amount',
        'progress_percentage',
        'status',
    ];

    public function household()
    {
        return $this->belongsTo(Household::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Accessor for remaining_amount
     */
    public function getRemainingAmountAttribute(): string
    {
        $target = (float) $this->target_amount;
        $current = (float) $this->current_amount;
        $remaining = max(0.0, $target - $current);

        return number_format($remaining, 2, '.', '');
    }

    /**
     * Accessor for progress_percentage
     */
    public function getProgressPercentageAttribute(): float
    {
        $target = (float) $this->target_amount;
        if ($target <= 0) {
            return 0.0;
        }

        $current = (float) $this->current_amount;
        $percentage = ($current / $target) * 100;

        return min(100.0, round($percentage, 2));
    }

    /**
     * Accessor for status
     */
    public function getStatusAttribute(): string
    {
        if ($this->is_completed || (float) $this->current_amount >= (float) $this->target_amount) {
            return 'completed';
        }

        if ($this->target_date && strtotime($this->target_date) < strtotime(date('Y-m-d'))) {
            return 'expired';
        }

        return 'active';
    }
}
