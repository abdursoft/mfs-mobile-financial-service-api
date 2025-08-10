<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Kyc extends Model
{
    protected $fillable = [
        'user_id',
        'user_image',
        'document_type',
        'document_number',
        'document_front_image',
        'document_back_image',
        'status',
        'host_url'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
