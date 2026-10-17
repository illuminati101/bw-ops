<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureStaff
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->user()?->isStaff() && $request->user()->status === 'active', 403);

        return $next($request);
    }
}
