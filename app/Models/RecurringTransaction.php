<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecurringTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'household_id',
        'type',
        'amount',
        'description',
        'frequency',
        'start_date',
        'end_date',
        'last_generated_at',
        'account_id',
        'to_account_id',
        'is_active',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'last_generated_at' => 'date:Y-m-d',
        'account_id' => 'integer',
        'to_account_id' => 'integer',
        'is_active' => 'boolean',
    ];

    public function household()
    {
        return $this->belongsTo(Household::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function toAccount()
    {
        return $this->belongsTo(Account::class, 'to_account_id');
    }
}
