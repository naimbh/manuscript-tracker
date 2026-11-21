<?php

use App\Http\Middleware\AuthenticateMcpToken;
use App\Mcp\Servers\TrackerServer;
use Laravel\Mcp\Facades\Mcp;

// Local agents (Claude Desktop, Claude Code, Cursor…): php artisan mcp:start tracker
Mcp::local('tracker', TrackerServer::class);

// Remote agents: POST /mcp with the agent token from Settings → Agents as a Bearer token.
Mcp::web('/mcp', TrackerServer::class)->middleware(AuthenticateMcpToken::class);
