<?php

namespace App\Services;

use App\Models\{PatientAiConversation, PatientAiTurn, Patient, User};
use Illuminate\Support\Facades\DB;

class PatientAiJournal
{
    public const CONSENT_VERSION = 'ibn-sina-lab-history-v1';

    /** Short database lock only; never keep a transaction open during provider I/O. */
    public function begin(User $lab, Patient $patient, array $input, array $context): array
    {
        return DB::transaction(function () use ($lab, $patient, $input, $context) {
            unset($context['patient_reported_concern']);
            $hash = hash('sha256', json_encode([$context, (bool) ($input['guided'] ?? false)]));
            PatientAiConversation::firstOrCreate(['id' => $input['conversation_id']], [
                'lab_id' => $lab->id, 'patient_id' => $patient->id, 'invoice_id' => $input['invoice_id'] ?? null,
                'consent_version' => self::CONSENT_VERSION, 'consented_at' => now(), 'context_hash' => $hash,
                'scope' => ['analyses' => array_column($context['report']['analyses'] ?? [], 'name')],
            ]);
            $conversation = PatientAiConversation::whereKey($input['conversation_id'])->lockForUpdate()->firstOrFail();
            abort_unless((int) $conversation->lab_id === (int) $lab->id && (int) $conversation->patient_id === (int) $patient->id, 404);
            if (!hash_equals($conversation->context_hash, $hash)) {
                abort(response()->json(['message' => 'تغيّر سياق التقرير؛ ابدأ محادثة جديدة.', 'context_reset' => true], 409));
            }
            $turn = $conversation->turns()->where('request_id', $input['request_id'])->first();
            if ($turn) {
                abort_unless($turn->message === $input['message'], 409, 'لا يمكن تغيير نص رسالة أُرسلت مسبقاً بنفس المعرّف.');
                if ($turn->status === 'answered') return [$turn, false];
                abort_if($turn->status === 'pending' && $turn->updated_at->gt(now()->subMinutes(2)), 409, 'الرسالة قيد المعالجة؛ انتظر قليلاً ثم أعد المحاولة.');
                $turn->update(['status' => 'pending', 'attempt' => $turn->attempt + 1]);
            } else {
                $turn = $conversation->turns()->create(['request_id' => $input['request_id'], 'message' => $input['message'], 'status' => 'pending']);
                $turn->refresh();
            }
            $conversation->touch();
            return [$turn, true];
        }, 3);
    }

    public function finish(PatientAiTurn $turn, ?array $answer): bool
    {
        return DB::transaction(function () use ($turn, $answer) {
            $current = PatientAiTurn::whereKey($turn->id)->lockForUpdate()->firstOrFail();
            if ($current->attempt !== $turn->attempt || $current->status !== 'pending') return false;
            $current->update(['answer' => $answer, 'status' => $answer === null ? 'failed' : 'answered']);
            PatientAiConversation::whereKey($turn->conversation_id)->update(['updated_at' => now()]);
            return true;
        });
    }
}
