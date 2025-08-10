<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Hash;

class JwtAuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'phone' => 'required',
            'password' => 'required',
        ]);

        $user = User::where('phone', $request->phone)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        $payload = [
            'iss' => "mfs-app",
            'sub' => $user->id,
            'iat' => time(),
            'exp' => time() + env('JWT_EXPIRY', 3600)
        ];

        $token = JWT::encode($payload, env('JWT_SECRET'), 'HS256');

        // store token & expiry in DB for revoke
        $user->api_token = $token;
        $user->token_expired_at = now()->addSeconds(env('JWT_EXPIRY', 3600));
        $user->save();

        return response()->json([
            'access_token' => $token,
            'expires_in' => env('JWT_EXPIRY', 3600)
        ]);
    }

    public function refresh(Request $request)
    {
        $user = $request->auth_user;
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $payload = [
            'iss' => "mfs-app",
            'sub' => $user->id,
            'iat' => time(),
            'exp' => time() + env('JWT_EXPIRY', 3600)
        ];

        $token = JWT::encode($payload, env('JWT_SECRET'), 'HS256');

        $user->api_token = $token;
        $user->token_expired_at = now()->addSeconds(env('JWT_EXPIRY', 3600));
        $user->save();

        return response()->json([
            'access_token' => $token,
            'expires_in' => env('JWT_EXPIRY', 3600)
        ]);
    }

    public function logout(Request $request)
    {
        $user = $request->auth_user;
        if ($user) {
            $user->api_token = null;
            $user->token_expired_at = null;
            $user->save();
        }

        return response()->json(['message' => 'Logged out successfully']);
    }
}
