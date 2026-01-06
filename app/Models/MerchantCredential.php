<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MerchantCredential extends Model
{
    protected $fillable = [
        'secret_key',
        'public_key',
        'app_name',
        'app_logo',
        'app_type',
        'status',
        'user_id',
    ];


    /**
     * Hidden the price id
     */
    protected $hidden = ['id'];

    protected $casts = [
        'webhook_events' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Add relation with webhook table
     */
    public function webhook(){
        return $this->hasOne(Webhook::class, 'merchant_app_id');
    }
}
