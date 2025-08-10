<?php

namespace App\Services;

class SmsService
{

    public static function cashIn($user, $amount)
    {
        return "You have successfully cashed in BDT {$amount}. Your new balance is BDT {$user->wallet->balance}.";
    }

    public static function cashOut($user, $amount)
    {
        return "You have successfully cashed out BDT {$amount}. Your new balance is BDT {$user->wallet->balance}.";

    }

    public static function sendMoney($user, $amount, $to_phone)
    {
        return "You have sent BDT {$amount} to {$to_phone}. Your new balance is BDT {$user->wallet->balance}.";

    }

    public static function receivedMoney($user, $amount, $from_phone)
    {
        return "You have received BDT {$amount} from {$from_phone}. Your new balance is BDT {$user->wallet->balance}.";

    }

    public static function payment($user, $amount, $merchant_name)
    {
        return "You have paid BDT {$amount} to merchant {$merchant_name}. Your new balance is BDT {$user->wallet->balance}.";

    }
}
