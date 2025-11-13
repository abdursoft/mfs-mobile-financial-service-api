<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Price extends Model
{
    use HasFactory;

    protected $fillable = [
        'price_id',
        'amount',
        'cycle',
        'currency',
        'merchant_app_id',
    ];

    /**
     * Relationship: A price belongs to a merchant app.
     */
    public function merchantApp()
    {
        return $this->belongsTo(MerchantCredential::class, 'merchant_app_id');
    }

    /**
     * Hidden the price id
     */
    protected $hidden = ['id'];
}
