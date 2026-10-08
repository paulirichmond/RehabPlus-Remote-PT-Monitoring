<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $user = Auth::user();

        if (! $user || ($roles !== [] && ! in_array($user->role, $roles, true))) {
            abort(403, 'Access denied.');
        }

        return $next($request);
    }
}
