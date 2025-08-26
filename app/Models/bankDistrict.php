<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BankDistrict extends Model
{
    use HasFactory;

    protected $table = 'bank_districts';

    protected $fillable = [
        'district_name',
        'district_slug',
        'bank_id',
    ];

    public $timestamps = true;

    /**
     * Relationship: A district belongs to a bank
     */
    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }
}
