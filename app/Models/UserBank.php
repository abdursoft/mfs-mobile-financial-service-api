<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserBank extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'bank_id',
        'bank_district_id',
        'district_branch_id',
    ];

    /**
     * The user who owns this record
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The bank related to this user
     */
    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }

    /**
     * The bank district related to this user
     */
    public function district()
    {
        return $this->belongsTo(BankDistrict::class, 'bank_district_id');
    }

    /**
     * The branch related to this user
     */
    public function branch()
    {
        return $this->belongsTo(DistrictBranch::class, 'district_branch_id');
    }
}
