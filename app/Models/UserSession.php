<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserSession extends Model
{
    protected $fillable = [
        'user_id',
        'session_id',
        'user_agent',
        'ip_address',
    ];

    /**
     * Relationship: Each session belongs to a user.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
