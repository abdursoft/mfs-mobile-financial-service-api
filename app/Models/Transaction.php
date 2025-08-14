<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = [
        'from_user_id',
        'to_user_id',
        'payment_id',
        'amount',
        'type',
        'status',
        'txn_id',
        'otp',
        'reference',
        'charge_amount',
        'interest',
    ];

    protected $hidden = [
        'otp',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'charge_amount' => 'decimal:2',
        'interest' => 'decimal:2',
    ];

    public function fromUser()
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser()
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function payment(){
        return $this->belongsTo(PaymentRequest::class);
    }
}
