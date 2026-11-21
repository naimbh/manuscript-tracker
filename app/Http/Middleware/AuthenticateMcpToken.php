<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Signs in the user whose agent token (Settings → Agents) is sent as a Bearer token. */
class AuthenticateMcpToken
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();
        $user = $token ? User::where('mcp_token', hash('sha256', $token))->first() : null;
        if (! $user) {
            return response()->json(['error' => 'Missing or invalid agent token.'], 401);
        }
        Auth::setUser($user);

        return $next($request);
    }
}
