<?php


// check Auth
if(!function_exists('authUser')){
    function authUser($request){
        return $request->attributes->get('auth_user');
    }
}

// check Auth
if(!function_exists('merchantApp')){
    function merchantApp($request){
        return $request->attributes->get('merchantApp');
    }
}

// unique random 32 charaters
if (!function_exists('generate_unique_token')) {
    function generate_unique_token($modelClass, $column = 'token', $type, $length = 32) {
        do {
            $token = \Illuminate\Support\Str::random($length);
        } while ($modelClass::where($column, $token)->exists());
        $kind = $type == 'development' ? '_test_' : '_live_';
        return $kind.$token;
    }
}

// unique random 32 charaters
if (!function_exists('txnID')) {
    function txnID($modelClass, $column = 'token', $length = 16) {
        do {
            $token = \Illuminate\Support\Str::random($length);
        } while ($modelClass::where($column, $token)->exists());
        return $token;
    }
}


// generate random otp
if(!function_exists('otp')){
    function otp(){
        return rand(100000,999999);
    }
}

// mask user phone number
if(!function_exists('maskPhone')){
    function maskPhone($phone){
        return substr($phone, 0, 3) . '****' . substr($phone, -4);
    }
}

// formatting phone number
if(!function_exists('format_phone')){
    function format_phone($phone){
        return substr($phone,0,5).'-'.substr($phone,5);
    }
}


// unique random 32 charaters
if (!function_exists('actID')) {
    function actID($modelClass, $column = 'act_id', $length = 32) {
        do {
            $token = \Illuminate\Support\Str::random($length);
        } while ($modelClass::where($column, $token)->exists());
        $kind = 'act_';
        return $kind.$token;
    }
}

// unique random 32 charaters
if (!function_exists('uniqueToken')) {
    function uniqueToken($modelClass, $column ,$prefix, $length = 32) {
        do {
            $token = \Illuminate\Support\Str::random($length);
        } while ($modelClass::where($column, $token)->exists());
        return $prefix.$token;
    }
}
