<?php

namespace App\Http\Controllers\Essentials;

use App\Http\Controllers\Controller;
use App\Models\Files;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserSession;
use DOMDocument;
use finfo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

class Helper extends Controller
{

    // authHelper
    public static function auth(Request $request)
    {
        if ((empty($request->bearerToken()) || ! $request->bearerToken() || $request->bearerToken() == '') && empty($request->query('auth'))) {
            return false;
        } else {
            $token = JWTAuth::verifyToken($request->bearerToken() ?? $request->query('auth'), false);
            if ($token && $token->id) {
                return User::select('id', 'name', 'bio', 'email', 'image', 'phone', 'role', 'is_verified')->find($token->id);
            }
            return false;
        }
    }

    // checking user login session
    public static function checkSession(Request $request, $user = null)
    {
        return UserSession::where('user_id', $request->user->id ?? $user)
            ->where('session_id', $request->bearerToken())
            ->count();
    }

}
