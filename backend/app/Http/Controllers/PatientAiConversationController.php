<?php

namespace App\Http\Controllers;

use App\Models\PatientAiConversation;
use App\Services\AccountAccess;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PatientAiConversationController extends Controller
{
    private function query(Request $request)
    {
        $user = $request->user();
        abort_unless($user && (in_array((int) $user->role_id, [1, 2], true) || $user->can('patient ai conversations view')), 403);
        // A request parameter can never broaden the authenticated tenant.
        return PatientAiConversation::where('lab_id', app(AccountAccess::class)->tenantId($user));
    }

    public function index(Request $request)
    {
        $query = $this->query($request);
        $input = $request->validate([
            'search' => 'nullable|string|max:100', 'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d'.($request->filled('from') ? '|after_or_equal:from' : ''), 'page' => 'nullable|integer|min:1',
        ]);
        if ($search = trim($input['search'] ?? '')) {
            $query->whereHas('patient', fn ($q) => $q->where('code', 'like', '%'.$search.'%')
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', '%'.$search.'%')));
        }
        if (!empty($input['from'])) $query->where('updated_at', '>=', CarbonImmutable::parse($input['from'], 'Asia/Baghdad')->startOfDay()->utc());
        if (!empty($input['to'])) $query->where('updated_at', '<', CarbonImmutable::parse($input['to'], 'Asia/Baghdad')->addDay()->startOfDay()->utc());
        $rows = $query->with(['patient.user', 'latestTurn'])->withCount('turns')->orderByDesc('updated_at')->orderByDesc('id')->paginate(20);
        return response()->json([
            'data' => $rows->getCollection()->map(fn ($c) => $this->summary($c) + [
                'message_count' => $c->turns_count, 'last_message' => Str::limit($c->latestTurn?->message ?? '', 140),
                'last_status' => $c->latestTurn?->status,
            ]),
            'current_page' => $rows->currentPage(), 'last_page' => $rows->lastPage(), 'total' => $rows->total(),
        ])->header('Cache-Control', 'private, no-store');
    }

    private function summary(PatientAiConversation $c): array
    {
        return ['id' => $c->id, 'patient_name' => $c->patient?->user?->name ?? 'مريض محذوف',
            'patient_code' => $c->patient?->code, 'invoice_id' => $c->invoice_id,
            'created_at' => $c->created_at->toIso8601String(), 'updated_at' => $c->updated_at->toIso8601String()];
    }

    public function show(Request $request, string $id)
    {
        $conversation = $this->query($request)->with('patient.user')->findOrFail($id);
        $request->validate(['page' => 'nullable|integer|min:1']);
        $turns = $conversation->turns()->orderBy('id')->paginate(30);
        // Metadata only: the activity log must never become a second transcript store.
        activity('patient-ai-access')->causedBy($request->user())->withProperties([
            'lab_id' => $conversation->lab_id, 'conversation_id' => $conversation->id,
        ])->event('action')->tap(function ($activity) use ($conversation) {
            $activity->audit_tenant_id = $conversation->lab_id;
            $activity->audit_source = 'server';
        })->log('عرض محادثة ابن سينا');
        return response()->json([
            'conversation' => $this->summary($conversation) + ['scope' => $conversation->scope, 'consented_at' => $conversation->consented_at->toIso8601String()],
            'data' => $turns->getCollection()->map(fn ($t) => [
                'id' => $t->id, 'message' => $t->message, 'reply' => $t->answer['reply'] ?? null,
                'urgent' => (bool) ($t->answer['urgent'] ?? false), 'status' => $t->status,
                'created_at' => $t->created_at->toIso8601String(), 'updated_at' => $t->updated_at->toIso8601String(),
            ]),
            'current_page' => $turns->currentPage(), 'last_page' => $turns->lastPage(), 'total' => $turns->total(),
        ])->header('Cache-Control', 'private, no-store');
    }
}
