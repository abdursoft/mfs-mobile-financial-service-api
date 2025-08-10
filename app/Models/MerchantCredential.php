<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MerchantCredential extends Model
{
    protected $fillable = [
        'secret_key',
        'public_key',
        'webhook_key',
        'webhook_url',
        'webhook_events',
        'app_name',
        'app_logo',
        'app_type',
        'status',
        'user_id',
    ];

    protected $casts = [
        'webhook_events' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
