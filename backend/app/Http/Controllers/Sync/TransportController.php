<?php

namespace App\Http\Controllers\Sync;

use App\Http\Controllers\Controller;
use App\Services\Sync\SyncLedger;
use Illuminate\Http\Request;

class TransportController extends Controller
{
    public function receive(Request $request, SyncLedger $ledger)
    {
        abort_unless($request->isJson(), 415);
        $body = json_decode($request->getContent(), true, 32);
        abort_unless(json_last_error() === JSON_ERROR_NONE && is_array($body) && !array_is_list($body), 422);
        return response()->json($ledger->receive($request->attributes->get('sync_peer_id'), $body));
    }

    public function acknowledge(Request $request, SyncLedger $ledger)
    {
        $data = $request->validate(['event_uuid' => ['required', 'uuid']]);
        $ledger->acknowledge($request->attributes->get('sync_peer_id'), $data['event_uuid'], $request->all());
        return response()->json(['acknowledged' => $data['event_uuid']]);
    }

    public function changes(Request $request, SyncLedger $ledger)
    {
        $data = $request->validate(['after' => ['required', 'integer', 'min:0', 'max:9007199254740991']]);
        return response()->json($ledger->changes($request->attributes->get('sync_peer_id'), (int) $data['after']));
    }
}
