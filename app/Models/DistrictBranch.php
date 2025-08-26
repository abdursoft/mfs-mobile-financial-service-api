<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DistrictBranch extends Model
{
    use HasFactory;

    // Mass assignable fields
    protected $fillable = [
        'branch_name',
        'branch_slug',
        'branch_code',
        'swift_code',
        'routing_number',
        'email',
        'fax',
        'telephone',
        'address',
        'bank_id',
        'bank_district_id',
    ];

    /**
     * Get the bank that owns this branch
     */
    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }

    /**
     * Get the district that owns this branch
     */
    public function district()
    {
        return $this->belongsTo(BankDistrict::class, 'bank_district_id');
    }
}
