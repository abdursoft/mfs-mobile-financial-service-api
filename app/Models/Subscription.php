<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'sub_id',
        'status',
        'price',
        'account_id',
        'product_id',
    ];


    /**
     * Hidden the price id
     */
    protected $hidden = ['id'];

    /**
     * A subscription belongs to an account.
     */
    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    /**
     * A subscription belongs to a product.
     */
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
