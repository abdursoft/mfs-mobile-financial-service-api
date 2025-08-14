<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionLimit extends Model
{
    protected $fillable = [
        'daily_send_money_limit',
        'daily_receive_money_limit',
        'daily_cash_in_limit',
        'daily_cash_out_limit',
        'daily_payment_limit',
        'monthly_send_money_limit',
        'monthly_cash_in_limit',
        'monthly_cash_out_limit',
        'monthly_payment_limit',
        'send_money_max',
        'cash_out_max',
        'payment_max',
        'user_id',
    ];

    protected $casts = [
        'daily_send_money_limit' => 'decimal:2',
        'daily_receive_money_limit' => 'decimal:2',
        'daily_cash_in_limit' => 'decimal:2',
        'daily_cash_out_limit' => 'decimal:2',
        'daily_payment_limit' => 'decimal:2',
        'monthly_send_money_limit' => 'decimal:2',
        'monthly_cash_in_limit' => 'decimal:2',
        'monthly_cash_out_limit' => 'decimal:2',
        'monthly_payment_limit' => 'decimal:2',
        'send_money_max' => 'decimal:2',
        'cash_out_max' => 'decimal:2',
        'payment_max' => 'decimal:2',
    ];

    /**
     * Get the user that owns the transaction limit.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
