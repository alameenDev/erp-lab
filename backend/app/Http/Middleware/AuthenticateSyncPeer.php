<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuthenticateSyncPeer
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless(config('lab_sync.enabled'), 404);
        abort_unless($request->isSecure() || app()->environment('testing'), 426, 'HTTPS required.');
        abort_if(strlen($request->getContent()) > 70000, 413);
        $token = $request->bearerToken();
        abort_unless(is_string($token) && strlen($token) >= 40 && strlen($token) <= 256, 401);
        $peer = DB::table('lab_sync_peers')->where('token_hash', hash('sha256', $token))
            ->where('enabled', true)->first();
        abort_unless($peer, 401);
        $request->attributes->set('sync_peer_id', (int) $peer->id);
        return $next($request);
    }
}
