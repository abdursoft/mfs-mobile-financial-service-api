<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserRevenue extends Model
{
    protected $fillable = [
        'note',
        'user_id',
        'amount'
    ];
}
