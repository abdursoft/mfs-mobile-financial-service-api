<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Essentials\JWTAuth;
use App\Models\MerchantCredential;
use Closure;
use App\Models\User;

class PaymentMiddleware
{
    public function handle($request, Closure $next)
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'code' => 'INVALID_TOKEN',
                'message' => 'Authorization token is missing'
            ], 401);
        }

        try {
            $decoded = JWTAuth::verifyToken($token, false);
            $merchant = MerchantCredential::where('id',$decoded->id)->first();

            // check revoked or expired
            if (!$merchant) {
                return response()->json([
                'code' => 'INVALID_TOKEN',
                'message' => 'Token is invalid or expire'
            ], 401);
            }

            if($decoded->role !== 'paymentToken'){
                return response()->json([
                    'code' => 'UNAUTHORIZED',
                    'message' => 'Unauthorized access!'
                ],401);
            }
            $request->attributes->set('merchantApp', $merchant); // set auth_user on request
            return $next($request);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 'INVALID_TOKEN',
                'message' => 'Token is invalid or expire',
                'error' => $e->getMessage()
            ], 401);
        }
    }
}
