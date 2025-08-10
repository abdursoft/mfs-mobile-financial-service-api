<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'merchant_id',
        'user_id',
        'txn_id',
        'mr_txn_id',
        'reference',
        'amount',
        'status',
        'expire_at'
    ];

    /**
     * The merchant that owns the payment request.
     */
    public function merchant()
    {
        return $this->belongsTo(User::class, 'merchant_id');
    }

    /**
     * The merchant that owns the payment request.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function transaction(){
        return $this->hasMany(Transaction::class);
    }
}
