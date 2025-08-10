<?php
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $fillable = [
        'name',
        'phone',
        'pin',
        'role',
        'otp',
        'image',
        'otp_hit',
        'password',
        'phone_verified_at',
    ];

    protected $hidden = [
        'pin',
        'otp',
        'otp_hit',
        'password',
        'api_token',
        'remember_token',
    ];

    protected $casts = [
        'phone_verified_at' => 'datetime',
        'otp_hit'           => 'integer',
        'otp'           => 'integer',
    ];

    public function wallet()
    {
        return $this->hasOne(Wallet::class);
    }

    public function transactionsFrom()
    {
        return $this->hasMany(Transaction::class, 'from_user_id');
    }

    public function transactionsTo()
    {
        return $this->hasMany(Transaction::class, 'to_user_id');
    }

    public function paymentRequestMerchant(){
        return $this->hasMany(PaymentRequest::class);
    }

    public function paymentRequestUser(){
        return $this->hasMany(PaymentRequest::class);
    }

    public function kyc()
    {
        return $this->hasOne(Kyc::class);
    }
}
