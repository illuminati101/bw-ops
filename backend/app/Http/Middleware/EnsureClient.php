<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureClient
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user && $user->isStaff()) {
            return redirect('/admin');
        }
        if (! $user || ! $user->isClient() || in_array($user->status, ['blocked'], true)) {
            Auth::guard('web')->logout();

            return redirect()->route('account.login');
        }

        return $next($request);
    }
}
