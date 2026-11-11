<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function handle(Request $request, Closure $next, string ...$keys): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Sesi tidak valid. Silakan masuk kembali.'], 401);
        }

        $granted = $user->permissionKeys();

        foreach ($keys as $key) {
            if (in_array($key, $granted, true)) {
                return $next($request);
            }
        }

        return response()->json(['message' => 'Anda tidak memiliki akses ke fitur ini.'], 403);
    }
}
