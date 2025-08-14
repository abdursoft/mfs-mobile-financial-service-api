<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionCharge extends Model
{
    protected $fillable = [
        'send_money_percentage',
        'cash_in_percentage',
        'cash_out_percentage',
        'payment_percentage',
        'send_money_fixed',
        'send_money_max',
        'description',
        'currency',
        'user_id',
    ];


    protected $casts = [
        'send_money_percentage' => 'decimal:2',
        'cash_in_percentage' => 'decimal:2',
        'cash_out_percentage' => 'decimal:2',
        'payment_percentage' => 'decimal:2',
        'send_money_fixed' => 'decimal:2',
        'send_money_max' => 'decimal:2',
    ];

    /**
     * Get the user that owns the transaction charge.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
