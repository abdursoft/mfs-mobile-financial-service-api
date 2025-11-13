<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'product_id',
        'short_description',
        'sku',
        'image',
        'price_id',
        'merchant_app_id',
    ];

    /**
     * Relationship: A product belongs to a price.
     */
    public function price()
    {
        return $this->belongsTo(Price::class, 'price_id');
    }

    /**
     * Relationship: A product belongs to a merchant app.
     */
    public function merchantApp()
    {
        return $this->belongsTo(MerchantCredential::class, 'merchant_app_id');
    }
}
