<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBranchScope
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user) {
            // Kasir is locked to their own branch
            if ($user->role === 'kasir') {
                config(['app.active_branch_id' => $user->branch_id]);
            } else {
                // Owner can switch branches via header
                $requestedBranch = $request->header('X-Branch-Id');
                if ($requestedBranch) {
                    config(['app.active_branch_id' => $requestedBranch]);
                } else {
                    // Default to user's branch (or Pusat if null)
                    config(['app.active_branch_id' => $user->branch_id ?? 1]);
                }
            }
        }

        return $next($request);
    }
}
