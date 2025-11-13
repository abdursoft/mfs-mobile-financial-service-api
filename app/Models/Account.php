<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    use HasFactory;

    protected $fillable = [
        'act_id',
        'name',
        'email',
        'phone',
        'country',
        'province',
        'state',
        'district',
        'zipcode',
        'village',
    ];
}
