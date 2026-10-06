<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Account extends Model
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
        'initial_balance',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'household_id' => 'integer',
        'initial_balance' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Get the household that owns the account.
     */
    public function household()
    {
        return $this->belongsTo(Household::class);
    }

    /**
     * Calculate derived current balance from transactions.
     *
     * @param string|null $asOfDate Optional cutoff date (YYYY-MM-DD)
     * @return string Formatted decimal string (e.g., '1250.50')
     */
    public function calculateBalance(?string $asOfDate = null): string
    {
        $householdId = $this->household_id;
        $accountId = $this->id;

        $incomeQuery = Transaction::where('household_id', $householdId)
            ->where('account_id', $accountId)
            ->where('type', 'income');

        $expenseQuery = Transaction::where('household_id', $householdId)
            ->where('account_id', $accountId)
            ->where('type', 'expense');

        $transferOutQuery = Transaction::where('household_id', $householdId)
            ->where('account_id', $accountId)
            ->where('type', 'transfer');

        $transferInQuery = Transaction::where('household_id', $householdId)
            ->where('to_account_id', $accountId)
            ->where('type', 'transfer');

        if (!is_null($asOfDate)) {
            $incomeQuery->whereDate('transaction_date', '<=', $asOfDate);
            $expenseQuery->whereDate('transaction_date', '<=', $asOfDate);
            $transferOutQuery->whereDate('transaction_date', '<=', $asOfDate);
            $transferInQuery->whereDate('transaction_date', '<=', $asOfDate);
        }

        $totalIncome = (float) ($incomeQuery->sum('amount') ?? 0);
        $totalExpense = (float) ($expenseQuery->sum('amount') ?? 0);
        $totalTransferOut = (float) ($transferOutQuery->sum('amount') ?? 0);
        $totalTransferIn = (float) ($transferInQuery->sum('amount') ?? 0);

        $initial = (float) ($this->initial_balance ?? 0);
        $balance = $initial + $totalIncome - $totalExpense - $totalTransferOut + $totalTransferIn;

        return number_format($balance, 2, '.', '');
    }

    /**
     * Accessor for derived current balance.
     *
     * @return string
     */
    public function getCurrentBalanceAttribute(): string
    {
        return $this->calculateBalance();
    }
}
