<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'txn_id',
        'mr_txn_id',
        'reference',
        'amount',
        'status',
        'expire_at',
        'cancel_url',
        'success_url',
        'merchant_app_id',
    ];

    /**
     * The merchant that owns the payment request.
     */
    public function merchantApp()
    {
        return $this->belongsTo(MerchantCredential::class, 'merchant_app_id');
    }

    /**
     * The merchant that owns the payment request.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function transaction(){
        return $this->hasOne(Transaction::class,'payment_id');
    }
}
