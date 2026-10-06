<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'household_id',
        'type',
        'amount',
        'description',
        'transaction_date',
        'account_id',
        'to_account_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'household_id' => 'integer',
        'amount' => 'decimal:2',
        'transaction_date' => 'date',
        'account_id' => 'integer',
        'to_account_id' => 'integer',
    ];

    /**
     * Get the household that owns the transaction.
     */
    public function household()
    {
        return $this->belongsTo(Household::class);
    }

    /**
     * Get the primary account related to the transaction.
     */
    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Get the destination account for transfer transactions.
     */
    public function toAccount()
    {
        return $this->belongsTo(Account::class, 'to_account_id');
    }
}

?>
