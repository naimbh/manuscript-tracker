<?php

namespace App\Mcp\Tools\Concerns;

use App\Models\User;
use Laravel\Mcp\Request;
use RuntimeException;

trait ResolvesUser
{
    /** The signed-in user over HTTP, or TRACKER_MCP_USER (else the first account) for the local server. */
    protected function owner(Request $request): User
    {
        if ($u = $request->user()) {
            return $u;
        }
        $email = config('tracker.mcp_user');
        $u = $email ? User::where('email', $email)->first() : User::orderBy('id')->first();

        return $u ?? throw new RuntimeException('No account found. Set TRACKER_MCP_USER to your login email.');
    }
}
