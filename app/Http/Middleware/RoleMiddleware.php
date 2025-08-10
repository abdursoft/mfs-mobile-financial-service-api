<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = $request->auth_user; // set earlier by jwt.auth middleware

        if (!$user || !in_array($user->role, $roles)) {
            return response()->json(['error' => 'Unauthorized. Insufficient role.'], 403);
        }

        return $next($request);
    }
}
