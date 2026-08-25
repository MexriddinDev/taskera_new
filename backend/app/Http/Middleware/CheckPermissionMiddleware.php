<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermissionMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Tizimga kirilmagan (Unauthenticated)'], 401);
        }

        // Super Admin gets access to all permissions
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        // Check if user has ANY of the specified permissions
        foreach ($permissions as $permission) {
            if ($user->hasPermission($permission)) {
                return $next($request);
            }
        }

        return response()->json([
            'message' => 'Sizda ushbu amalni bajarish uchun huquq yetarli emas (Talab etiladigan ruxsat: ' . implode(', ', $permissions) . ')',
        ], 403);
    }
}
