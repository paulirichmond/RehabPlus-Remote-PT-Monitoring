<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;

class EnsureAuthenticated
{
    public function handle(Request $request, Closure $next)
    {
        if (! Auth::check()) {
            return redirect()->route('login.page')->with('error', 'Please log in first.');
        }

        // Every log line from here on says who was signed in.
        Context::add('user_uid', Auth::id());
        Context::add('user_role', Auth::user()->role);

        return $next($request);
    }
}
