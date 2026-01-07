<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'غير مسجل دخول'], 401);
        }

        if (!in_array($user->role, $roles)) {
            return response()->json(['message' => 'ليس لديك صلاحية للوصول'], 403);
        }

        return $next($request);
    }
}
