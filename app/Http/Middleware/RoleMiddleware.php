<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     * Usage: middleware('role:owner') or middleware('role:kasir')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (!$request->user() || !in_array($request->user()->role, $roles)) {
            return response()->json([
                'message' => 'Akses ditolak. Anda tidak memiliki izin untuk mengakses halaman ini.',
            ], 403);
        }

        return $next($request);
    }
}
