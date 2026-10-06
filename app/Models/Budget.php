<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use App\Models\Transaction;

class Budget extends Model
{
    use HasFactory;

    protected $fillable = [
        'household_id',
        'category_id',
        'name',
        'amount',
        'period_type',
        'start_date',
        'end_date',
        'is_active',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'is_active' => 'boolean',
    ];

    protected $appends = [
        'spent_amount',
        'remaining_amount',
        'spent_percentage',
    ];

    public function household()
    {
        return $this->belongsTo(Household::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Calculate spent amount derived from expense transactions in period.
     */
    public function calculateSpent(): string
    {
        $query = Transaction::where('household_id', $this->household_id)
            ->where('type', 'expense')
            ->whereDate('transaction_date', '>=', $this->start_date)
            ->whereDate('transaction_date', '<=', $this->end_date);

        if (! is_null($this->category_id) && Schema::hasColumn('transactions', 'category_id')) {
            $query->where('category_id', $this->category_id);
        }

        $totalSpent = $query->sum('amount');

        return number_format((float) $totalSpent, 2, '.', '');
    }

    /**
     * Accessor for spent_amount
     */
    public function getSpentAmountAttribute(): string
    {
        return $this->calculateSpent();
    }

    /**
     * Accessor for remaining_amount
     */
    public function getRemainingAmountAttribute(): string
    {
        $spent = (float) $this->calculateSpent();
        $budgeted = (float) $this->amount;
        $remaining = max(0.0, $budgeted - $spent);

        return number_format($remaining, 2, '.', '');
    }

    /**
     * Accessor for spent_percentage
     */
    public function getSpentPercentageAttribute(): float
    {
        $budgeted = (float) $this->amount;
        if ($budgeted <= 0) {
            return 0.0;
        }

        $spent = (float) $this->calculateSpent();
        $percentage = ($spent / $budgeted) * 100;

        return round($percentage, 2);
    }
}
