<?php

namespace App\Http\Controllers\Sync;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StatusController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        // Owner's own scope only; staff and global cross-lab access are not implicit.
        abort_unless($user && in_array((int) $user->role_id, [1, 2], true), 403);
        $peer = DB::table('lab_sync_peers')->where('lab_id', $user->id)->first();
        $events = DB::table('lab_sync_events')->where('peer_id', $peer?->id ?? 0);
        return response()->json([
            'mode' => 'staging_only', 'clinical_sync_ready' => false,
            'transport_enabled' => (bool) config('lab_sync.enabled') && (bool) ($peer?->enabled ?? false),
            'paired' => $peer !== null,
            'pending' => (clone $events)->where('outbound', true)->whereNull('delivered_at')->count(),
            'conflicts' => (clone $events)->where(function ($q) { $q->where('state', 'conflict')->orWhere('remote_state', 'conflict'); })->count(),
            'last_contact_at' => $peer?->last_contact_at,
            'last_error' => $peer?->last_error,
        ]);
    }
}
