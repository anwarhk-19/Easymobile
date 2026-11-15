<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Blocks suspended accounts from using any authenticated endpoint. */
class EnsureActiveAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user && ! $user->isActive()) {
            $user->currentAccessToken()?->delete();

            return response()->json(['message' => 'Your account has been suspended. Please contact support.', 'code' => 'account_suspended'], 403);
        }

        return $next($request);
    }
}
