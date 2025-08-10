<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Essentials\JWTAuth;
use Closure;
use App\Models\User;

class AuthMiddleware
{
    public function handle($request, Closure $next)
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['error' => 'Token not provided'], 401);
        }

        try {
            $decoded = JWTAuth::verifyToken($token, false);
            $user = User::find($decoded->id);

            // check revoked or expired
            if (!$user || $user->api_token !== $token || $user->token_expired_at < now()) {
                return response()->json(['error' => 'Invalid or expired token'], 401);
            }

            if($user->role !== 'user'){
                return response()->json([
                    'code' => 'UNAUTHORIZED',
                    'message' => 'Unauthorized access!'
                ],401);
            }
            $request->attributes->set('auth_user', $user); // set auth_user on request
            return $next($request);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 'INVALID_TOKEN',
                'message' => 'Token invalid or expire'
            ], 401);
        }
    }
}
