<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Webhook extends Model
{
    use HasFactory;

    protected $fillable = [
        'webhook',
        'webhook_key',
        'webhook_sec',
        'webhook_url',
        'webhook_events',
        'webhook_type',
        'merchant_app_id',
    ];

    protected $casts = [
        'webhook_events' => 'array',
    ];

    protected $hidden = [
        'merchant_app_id',
    ];

    /**
     * Relationship: each webhook belongs to a merchant app
     */
    public function merchantApp()
    {
        return $this->belongsTo(MerchantCredential::class, 'merchant_app_id');
    }
}
